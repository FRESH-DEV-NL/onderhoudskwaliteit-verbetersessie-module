<?php
/**
 * AJAX Handler Class
 * 
 * Handles all AJAX requests for the plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class OVM_Ajax_Handler {
    
    /**
     * Instance of this class
     */
    private static $instance = null;
    
    /**
     * Data manager instance
     */
    private $data_manager;
    
    /**
     * Get the singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->data_manager = OVM_Data_Manager::get_instance();
    }
    
    /**
     * Verify AJAX request
     */
    private function verify_ajax_request() {
        if (!check_ajax_referer('ovm_ajax_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Ongeldige nonce', 'onderhoudskwaliteit-verbetersessie')));
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Onvoldoende rechten', 'onderhoudskwaliteit-verbetersessie')));
        }
    }
    
    /**
     * Save admin response
     */
    public function save_admin_response() {
        $this->verify_ajax_request();
        
        $comment_id = isset($_POST['comment_id']) ? intval($_POST['comment_id']) : 0;
        $response = isset($_POST['response']) ? sanitize_textarea_field($_POST['response']) : '';
        
        if (!$comment_id) {
            wp_send_json_error(array('message' => __('Ongeldige comment ID', 'onderhoudskwaliteit-verbetersessie')));
        }
        
        $result = $this->data_manager->update_comment($comment_id, array(
            'admin_response' => $response
        ));
        
        if ($result !== false) {
            wp_send_json_success(array(
                'message' => __('Reactie opgeslagen', 'onderhoudskwaliteit-verbetersessie'),
                'response' => $response
            ));
        } else {
            wp_send_json_error(array('message' => __('Fout bij opslaan', 'onderhoudskwaliteit-verbetersessie')));
        }
    }
    
    /**
     * Change comment status
     */
    public function change_status() {
        $this->verify_ajax_request();
        
        $comment_id = isset($_POST['comment_id']) ? intval($_POST['comment_id']) : 0;
        $action = isset($_POST['action_type']) ? sanitize_text_field($_POST['action_type']) : '';
        
        if (!$comment_id || !$action) {
            wp_send_json_error(array('message' => __('Ongeldige parameters', 'onderhoudskwaliteit-verbetersessie')));
        }
        
        $comment = $this->data_manager->get_comment($comment_id);
        
        if (!$comment) {
            wp_send_json_error(array('message' => __('Comment niet gevonden', 'onderhoudskwaliteit-verbetersessie')));
        }
        
        $new_status = $this->get_new_status_from_action($action, $comment->status);
        
        if ($action === 'move_to_export' && empty($comment->admin_response)) {
            wp_send_json_error(array('message' => __('Een reactie is vereist voordat je naar "Klaar voor export" kunt verplaatsen', 'onderhoudskwaliteit-verbetersessie')));
        }
        
        if (!$new_status) {
            wp_send_json_error(array('message' => __('Ongeldige actie', 'onderhoudskwaliteit-verbetersessie')));
        }
        
        $result = $this->data_manager->update_comment($comment_id, array(
            'status' => $new_status
        ));
        
        if ($result !== false) {
            wp_send_json_success(array(
                'message' => __('Status gewijzigd', 'onderhoudskwaliteit-verbetersessie'),
                'new_status' => $new_status
            ));
        } else {
            wp_send_json_error(array('message' => __('Fout bij wijzigen status', 'onderhoudskwaliteit-verbetersessie')));
        }
    }
    
    /**
     * Handle bulk action
     */
    public function handle_bulk_action() {
        $this->verify_ajax_request();
        
        $action = isset($_POST['bulk_action']) ? sanitize_text_field($_POST['bulk_action']) : '';
        $comment_ids = isset($_POST['comment_ids']) ? array_map('intval', $_POST['comment_ids']) : array();
        
        if (!$action || empty($comment_ids)) {
            wp_send_json_error(array('message' => __('Ongeldige parameters', 'onderhoudskwaliteit-verbetersessie')));
        }
        
        if ($action === 'delete') {
            $success = 0;
            foreach ($comment_ids as $id) {
                if ($this->data_manager->delete_comment($id)) {
                    $success++;
                }
            }
            
            wp_send_json_success(array(
                'message' => sprintf(__('%d opmerkingen verwijderd', 'onderhoudskwaliteit-verbetersessie'), $success),
                'deleted' => $success
            ));
        } else {
            if ($action === 'move_to_export') {
                foreach ($comment_ids as $id) {
                    $comment = $this->data_manager->get_comment($id);
                    if ($comment && empty($comment->admin_response)) {
                        wp_send_json_error(array(
                            'message' => __('Sommige opmerkingen hebben geen reactie. Voeg eerst een reactie toe.', 'onderhoudskwaliteit-verbetersessie')
                        ));
                    }
                }
            }
            
            $current_status = isset($_POST['current_status']) ? sanitize_text_field($_POST['current_status']) : '';
            $new_status = $this->get_new_status_from_action($action, $current_status);
            
            if (!$new_status) {
                wp_send_json_error(array('message' => __('Ongeldige actie', 'onderhoudskwaliteit-verbetersessie')));
            }
            
            $result = $this->data_manager->bulk_update_status($comment_ids, $new_status);
            
            if ($result !== false) {
                wp_send_json_success(array(
                    'message' => sprintf(__('%d opmerkingen verplaatst', 'onderhoudskwaliteit-verbetersessie'), count($comment_ids)),
                    'new_status' => $new_status
                ));
            } else {
                wp_send_json_error(array('message' => __('Fout bij bulk actie', 'onderhoudskwaliteit-verbetersessie')));
            }
        }
    }
    
    /**
     * Filter comments by page
     */
    public function filter_by_page() {
        $this->verify_ajax_request();
        
        $page_id = isset($_POST['page_id']) ? intval($_POST['page_id']) : null;
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : 'te_verwerken';
        
        ob_start();
        $admin_page = OVM_Admin_Page::get_instance();
        $method = new ReflectionMethod($admin_page, 'render_comments_rows');
        $method->setAccessible(true);
        $method->invoke($admin_page, $status, $page_id);
        $html = ob_get_clean();
        
        wp_send_json_success(array(
            'html' => $html
        ));
    }
    
    /**
     * Delete comment
     */
    public function delete_comment() {
        $this->verify_ajax_request();
        
        $comment_id = isset($_POST['comment_id']) ? intval($_POST['comment_id']) : 0;
        
        if (!$comment_id) {
            wp_send_json_error(array('message' => __('Ongeldige comment ID', 'onderhoudskwaliteit-verbetersessie')));
        }
        
        $result = $this->data_manager->delete_comment($comment_id);
        
        if ($result) {
            wp_send_json_success(array(
                'message' => __('Opmerking verwijderd', 'onderhoudskwaliteit-verbetersessie')
            ));
        } else {
            wp_send_json_error(array('message' => __('Fout bij verwijderen', 'onderhoudskwaliteit-verbetersessie')));
        }
    }
    
    /**
     * Save comment content
     */
    public function save_comment_content() {
        $this->verify_ajax_request();
        
        $comment_id = isset($_POST['comment_id']) ? intval($_POST['comment_id']) : 0;
        $content = isset($_POST['content']) ? sanitize_textarea_field($_POST['content']) : '';
        
        if (!$comment_id) {
            wp_send_json_error(array('message' => __('Ongeldige comment ID', 'onderhoudskwaliteit-verbetersessie')));
        }
        
        // Strip content like the original method
        $content = wp_strip_all_tags($content);
        $content = preg_replace('/\s+/', ' ', $content);
        $content = trim($content);
        
        $result = $this->data_manager->update_comment($comment_id, array(
            'comment_content' => $content
        ));
        
        if ($result !== false) {
            // Return truncated version for display
            $truncated_content = wp_trim_words($content, 30, '...');
            $has_more = strlen($content) > 200;
            
            wp_send_json_success(array(
                'message' => __('Opmerking opgeslagen', 'onderhoudskwaliteit-verbetersessie'),
                'content' => $content,
                'truncated' => $truncated_content,
                'has_more' => $has_more
            ));
        } else {
            wp_send_json_error(array('message' => __('Fout bij opslaan opmerking', 'onderhoudskwaliteit-verbetersessie')));
        }
    }
    
    /**
     * Import comments
     */
    public function import_comments() {
        $this->verify_ajax_request();
        
        // Get batch parameters
        $batch_size = 50; // Process 50 comments at a time
        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
        $total_processed = isset($_POST['total_processed']) ? intval($_POST['total_processed']) : 0;
        
        // Get all approved comments if first batch
        if ($offset === 0) {
            $all_comments = get_comments(array(
                'status' => 'approve',
                'number' => 0,
                'count' => true
            ));
            $total_comments = $all_comments;
        } else {
            $total_comments = isset($_POST['total_comments']) ? intval($_POST['total_comments']) : 0;
        }
        
        // Get batch of comments
        $comments = get_comments(array(
            'status' => 'approve',
            'number' => $batch_size,
            'offset' => $offset,
            'orderby' => 'comment_ID',
            'order' => 'ASC'
        ));
        
        $imported_in_batch = 0;
        $skipped_in_batch = 0;
        
        foreach ($comments as $comment) {
            // Check if comment already exists
            if ($this->data_manager->comment_exists($comment->comment_ID)) {
                $skipped_in_batch++;
                continue;
            }
            
            $post = get_post($comment->comment_post_ID);
            if (!$post) {
                $skipped_in_batch++;
                continue;
            }
            
            // Extract rating if available
            $rating = get_comment_meta($comment->comment_ID, 'rating', true);
            $rating = $rating ? intval($rating) : null;
            
            $metadata = array(
                'imported_manually' => current_time('mysql'),
                'original_status' => $comment->comment_approved,
                'batch_import' => true
            );
            
            $data = array(
                'comment_id' => $comment->comment_ID,
                'post_id' => $comment->comment_post_ID,
                'post_title' => $post->post_title,
                'author_name' => $comment->comment_author,
                'author_email' => $comment->comment_author_email,
                'author_ip' => $comment->comment_author_IP,
                'comment_content' => $comment->comment_content,
                'comment_date' => $comment->comment_date,
                'rating' => $rating,
                'admin_response' => '',
                'status' => 'te_verwerken',
                'status_changed_date' => current_time('mysql'),
                'metadata' => $metadata
            );
            
            if ($this->data_manager->insert_comment($data)) {
                $imported_in_batch++;
            } else {
                $skipped_in_batch++;
            }
        }
        
        $new_total_processed = $total_processed + $imported_in_batch;
        $new_offset = $offset + $batch_size;
        $has_more = count($comments) === $batch_size;
        
        // If this is the first batch, get actual total count
        if ($offset === 0 && $total_comments === 0) {
            $total_comments = get_comments(array(
                'status' => 'approve',
                'number' => 0,
                'count' => true
            ));
        }
        
        $response_data = array(
            'imported_in_batch' => $imported_in_batch,
            'skipped_in_batch' => $skipped_in_batch,
            'total_processed' => $new_total_processed,
            'total_comments' => $total_comments,
            'offset' => $new_offset,
            'has_more' => $has_more,
            'progress_percentage' => $total_comments > 0 ? round(($new_offset / $total_comments) * 100) : 100
        );
        
        if (!$has_more) {
            // Import completed
            $response_data['message'] = sprintf(
                __('%d nieuwe comments zijn geïmporteerd. %d duplicaten overgeslagen.', 'onderhoudskwaliteit-verbetersessie'),
                $new_total_processed,
                $total_processed + $skipped_in_batch - $new_total_processed
            );
            $response_data['completed'] = true;
            
            // Update import flag
            update_option('ovm_last_manual_import', current_time('mysql'));
        } else {
            $response_data['message'] = sprintf(
                __('Bezig met importeren... %d van %d comments verwerkt', 'onderhoudskwaliteit-verbetersessie'),
                $new_offset,
                $total_comments
            );
        }
        
        wp_send_json_success($response_data);
    }
    
    /**
     * Get new status from action
     */
    private function get_new_status_from_action($action, $current_status) {
        $status_map = array(
            'move_to_export' => 'klaar_voor_export',
            'move_to_completed' => 'afgerond',
            'move_to_processing' => 'te_verwerken'
        );
        
        return isset($status_map[$action]) ? $status_map[$action] : false;
    }
}