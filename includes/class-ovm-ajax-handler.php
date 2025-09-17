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
        
        // Check if comment can be edited (only te_verwerken status)
        $comment = $this->data_manager->get_comment($comment_id);
        if (!$comment) {
            wp_send_json_error(array('message' => __('Comment niet gevonden', 'onderhoudskwaliteit-verbetersessie')));
        }
        
        if ($comment->status !== 'te_verwerken') {
            wp_send_json_error(array('message' => __('Alleen opmerkingen met status "Te verwerken" kunnen worden bewerkt', 'onderhoudskwaliteit-verbetersessie')));
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
        
        // Special handling for delete_wp_comment action
        if ($action === 'delete_wp_comment') {
            // Check if status is 'afgerond'
            if ($comment->status !== 'afgerond') {
                wp_send_json_error(array('message' => __('Alleen afgeronde comments kunnen uit WordPress worden verwijderd', 'onderhoudskwaliteit-verbetersessie')));
            }
            
            // Delete WordPress comment if it exists
            if ($comment->comment_id) {
                if (wp_delete_comment($comment->comment_id, true)) {
                    // Update record to clear comment_id
                    $this->data_manager->update_comment($comment_id, array(
                        'comment_id' => null
                    ));
                    
                    wp_send_json_success(array(
                        'message' => __('WordPress comment verwijderd', 'onderhoudskwaliteit-verbetersessie'),
                        'status' => $comment->status
                    ));
                } else {
                    wp_send_json_error(array('message' => __('Kon WordPress comment niet verwijderen', 'onderhoudskwaliteit-verbetersessie')));
                }
            } else {
                wp_send_json_error(array('message' => __('Geen WordPress comment gevonden om te verwijderen', 'onderhoudskwaliteit-verbetersessie')));
            }
            return;
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
        } else if ($action === 'delete_wp_comments') {
            // Only allow for completed items
            $success = 0;
            $wp_deleted = 0;
            $errors = array();
            
            foreach ($comment_ids as $id) {
                $comment = $this->data_manager->get_comment($id);
                
                if (!$comment) {
                    $errors[] = sprintf(__('Comment %d niet gevonden', 'onderhoudskwaliteit-verbetersessie'), $id);
                    continue;
                }
                
                // Check if status is 'afgerond'
                if ($comment->status !== 'afgerond') {
                    $errors[] = sprintf(__('Comment %d is niet afgerond', 'onderhoudskwaliteit-verbetersessie'), $id);
                    continue;
                }
                
                // Delete WordPress comment if it exists
                if ($comment->comment_id && wp_delete_comment($comment->comment_id, true)) {
                    $wp_deleted++;
                    
                    // Update record to clear comment_id
                    $this->data_manager->update_comment($id, array(
                        'comment_id' => null
                    ));
                    
                    $success++;
                }
            }
            
            $message = sprintf(__('%d WordPress comments verwijderd', 'onderhoudskwaliteit-verbetersessie'), $wp_deleted);
            if (!empty($errors)) {
                $message .= ' - ' . implode(', ', $errors);
            }
            
            wp_send_json_success(array(
                'message' => $message,
                'deleted' => $success,
                'errors' => $errors
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
     * Get posts for a specific status
     */
    public function get_posts_for_status() {
        $this->verify_ajax_request();
        
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : 'te_verwerken';
        
        $posts = $this->data_manager->get_posts_with_comments($status);
        
        wp_send_json_success(array(
            'posts' => $posts
        ));
    }
    
    /**
     * Generate ChatGPT response
     */
    public function chatgpt_generate_response() {
        $this->verify_ajax_request();
        
        $comment_id = isset($_POST['comment_id']) ? intval($_POST['comment_id']) : 0;
        
        if (!$comment_id) {
            wp_send_json_error(array('message' => __('Ongeldige comment ID', 'onderhoudskwaliteit-verbetersessie')));
        }
        
        // Get API key and prompt from settings
        $api_key = get_option('ovm_chatgpt_api_key', '');
        $prompt_template = get_option('ovm_chatgpt_prompt', 'Herschrijf deze tekst maar behoud de toon en zorg dat er geen spelfouten in de tekst zit: [reactie_tekst]');
        
        if (empty($api_key)) {
            wp_send_json_error(array('message' => __('ChatGPT API key niet geconfigureerd. Ga naar Instellingen om de API key in te stellen.', 'onderhoudskwaliteit-verbetersessie')));
        }
        
        // Get comment content
        $comment = $this->data_manager->get_comment($comment_id);
        if (!$comment) {
            wp_send_json_error(array('message' => __('Comment niet gevonden', 'onderhoudskwaliteit-verbetersessie')));
        }
        
        // Use admin response text instead of comment content
        $admin_response_text = $comment->admin_response ?: '';
        
        if (empty($admin_response_text)) {
            wp_send_json_error(array('message' => __('Voer eerst een reactie in voordat je ChatGPT gebruikt om deze te verbeteren.', 'onderhoudskwaliteit-verbetersessie')));
        }
        
        // Replace placeholder in prompt with admin response
        $prompt = str_replace('[reactie_tekst]', $admin_response_text, $prompt_template);
        
        // Make API call to OpenAI
        $response = $this->call_openai_api($api_key, $prompt);
        
        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => $response->get_error_message()));
        }
        
        // Save the generated response
        $result = $this->data_manager->update_comment($comment_id, array(
            'admin_response' => $response
        ));
        
        if ($result !== false) {
            wp_send_json_success(array(
                'message' => __('ChatGPT reactie gegenereerd en opgeslagen', 'onderhoudskwaliteit-verbetersessie'),
                'response' => $response
            ));
        } else {
            wp_send_json_error(array('message' => __('Fout bij opslaan van gegenereerde reactie', 'onderhoudskwaliteit-verbetersessie')));
        }
    }
    
    /**
     * Call OpenAI API
     */
    private function call_openai_api($api_key, $prompt) {
        $url = 'https://api.openai.com/v1/chat/completions';
        
        $body = array(
            'model' => 'gpt-3.5-turbo',
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => $prompt
                )
            ),
            'max_tokens' => 500,
            'temperature' => 0.7
        );
        
        $args = array(
            'method' => 'POST',
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($body),
            'timeout' => 30
        );
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            return new WP_Error('api_error', __('Fout bij verbinding met ChatGPT API: ', 'onderhoudskwaliteit-verbetersessie') . $response->get_error_message());
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($response_code !== 200) {
            $error_data = json_decode($response_body, true);
            $error_message = isset($error_data['error']['message']) ? $error_data['error']['message'] : __('Onbekende API fout', 'onderhoudskwaliteit-verbetersessie');
            return new WP_Error('api_error', __('ChatGPT API fout: ', 'onderhoudskwaliteit-verbetersessie') . $error_message);
        }
        
        $data = json_decode($response_body, true);
        
        if (!isset($data['choices'][0]['message']['content'])) {
            return new WP_Error('api_error', __('Onverwacht API antwoord formaat', 'onderhoudskwaliteit-verbetersessie'));
        }
        
        return trim($data['choices'][0]['message']['content']);
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
        
        // Check if comment can be edited (only te_verwerken status)
        $comment = $this->data_manager->get_comment($comment_id);
        if (!$comment) {
            wp_send_json_error(array('message' => __('Comment niet gevonden', 'onderhoudskwaliteit-verbetersessie')));
        }
        
        if ($comment->status !== 'te_verwerken') {
            wp_send_json_error(array('message' => __('Alleen opmerkingen met status "Te verwerken" kunnen worden bewerkt', 'onderhoudskwaliteit-verbetersessie')));
        }
        
        // Strip content but preserve line breaks
        $content = preg_replace('/<br\s*\/?>/i', "\n", $content);
        $content = preg_replace('/<\/p>/i', "\n", $content);
        $content = preg_replace('/<p[^>]*>/i', '', $content);
        $content = wp_strip_all_tags($content);
        
        // Normalize line endings
        $content = str_replace(array("\r\n", "\r"), "\n", $content);
        
        // Replace multiple consecutive newlines (3 or more) with maximum 2 newlines
        $content = preg_replace("/\n{3,}/", "\n\n", $content);
        
        // Remove leading/trailing whitespace per line
        $lines = explode("\n", $content);
        $lines = array_map('trim', $lines);
        $content = implode("\n", $lines);
        
        // Remove excessive spaces within lines
        $lines = explode("\n", $content);
        foreach ($lines as &$line) {
            $line = preg_replace('/[ \t]{2,}/', ' ', $line);
        }
        $content = implode("\n", $lines);
        
        // Preserve list formatting
        $content = preg_replace('/\n\s*[-–*•]\s+/', "\n– ", $content);
        
        $content = trim($content);
        
        $result = $this->data_manager->update_comment($comment_id, array(
            'comment_content' => $content
        ));
        
        if ($result !== false) {
            // Create truncated version that preserves line breaks
            $truncated_content = $this->truncate_with_formatting($content, 200);
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
            
            // Detect images in comment
            $images = array();
            
            // Check for IMG tags in content
            preg_match_all('/<img[^>]+src=[\'"]([^\'"]+)[\'"][^>]*>/i', $comment->comment_content, $matches);
            if (!empty($matches[1])) {
                foreach ($matches[1] as $img_url) {
                    $images[] = array(
                        'url' => esc_url_raw($img_url),
                        'type' => 'embedded'
                    );
                }
            }
            
            // Check for WordPress comment attachments (multiple possible meta keys)
            $meta_keys = array('comment_attachment', 'dco_attachment_id', 'attachment_id');
            foreach ($meta_keys as $meta_key) {
                $attachments = get_comment_meta($comment->comment_ID, $meta_key, false);
                if (!empty($attachments)) {
                    foreach ($attachments as $attachment_id) {
                        if (!empty($attachment_id)) {
                            $attachment_url = wp_get_attachment_url($attachment_id);
                            if ($attachment_url) {
                                // Get full size URL for better quality
                                $full_url = wp_get_attachment_image_url($attachment_id, 'full');
                                $images[] = array(
                                    'url' => esc_url_raw($full_url ?: $attachment_url),
                                    'type' => 'attachment',
                                    'attachment_id' => $attachment_id,
                                    'meta_key' => $meta_key
                                );
                            }
                        }
                    }
                }
            }
            
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
                'metadata' => $metadata,
                'images' => !empty($images) ? json_encode($images) : null
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
     * Update missing images
     */
    public function update_missing_images() {
        $this->verify_ajax_request();
        
        $updated = $this->data_manager->update_missing_images();
        
        wp_send_json_success(array(
            'message' => sprintf(__('%d comments bijgewerkt met afbeeldingen', 'onderhoudskwaliteit-verbetersessie'), $updated),
            'updated' => $updated
        ));
    }
    
    /**
     * Export comments to PDF
     */
    public function export_comments() {
        $this->verify_ajax_request();
        
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : 'klaar_voor_export';
        
        // Get comments for export
        $comments = $this->data_manager->get_comments_by_status($status);
        
        if (empty($comments)) {
            wp_send_json_error(array('message' => __('Geen opmerkingen om te exporteren', 'onderhoudskwaliteit-verbetersessie')));
        }
        
        // Sort comments by article name (post_title) in alphabetical order
        usort($comments, function($a, $b) {
            return strcasecmp($a->post_title, $b->post_title);
        });
        
        // Load mPDF autoloader
        require_once OVM_PLUGIN_DIR . 'lib/mpdf-autoload.php';
        
        try {
            // Create new mPDF instance with optimized landscape settings and UTF-8 support
            $mpdf = new \Mpdf\Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'orientation' => 'L', // Landscape orientation
                'margin_left' => 12,  // Minimal margins for maximum space
                'margin_right' => 12,
                'margin_top' => 15,
                'margin_bottom' => 15,
                'margin_header' => 0,
                'margin_footer' => 0,
                'tempDir' => OVM_PLUGIN_DIR . 'lib/tmp/mpdf',
                'default_font' => 'dejavusans',
                'autoScriptToLang' => true,
                'autoLangToFont' => true
            ]);
            
            // Set document information (minimal)
            $mpdf->SetTitle('CVS samenvatting - ' . date('Y-m-d'));
            $mpdf->SetAuthor('');
            $mpdf->SetCreator('');
            
            // Get export date for title
            $export_date = date('Y-m-d');
            
            // Get logo URL from settings
            $logo_url = get_option('ovm_logo_url', '');
            
            // Build clean HTML content - landscape optimized
            $html = '
            <style>
                body { 
                    font-family: DejaVu Sans, sans-serif; 
                    font-size: 10pt; 
                    line-height: 1.3;
                    color: #111;
                    margin: 0;
                    padding: 0;
                }
                
                .header {
                    display: table;
                    width: 100%;
                    margin-bottom: 10px;
                }
                
                .logo-container {
                    display: table-cell;
                    width: 50px;
                    vertical-align: middle;
                    padding-right: 10px;
                }
                
                .logo-container img {
                    max-width: 40px;
                    max-height: 20px;
                    height: auto;
                    width: auto;
                }
                
                .title-container {
                    display: table-cell;
                    vertical-align: middle;
                }
                
                h1 {
                    font-size: 14pt;
                    font-weight: bold;
                    margin: 0;
                    color: #111;
                    display: inline;
                }
                
                .date {
                    font-size: 9pt;
                    color: #666;
                    margin-left: 15px;
                    display: inline;
                }
                
                table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-top: 8px;
                    table-layout: fixed;
                }
                
                th, td {
                    text-align: left;
                    vertical-align: top;
                    padding: 5px 3px;
                    border: 1px solid #C9CCD1;
                    word-wrap: break-word;
                    overflow-wrap: break-word;
                    font-size: 7pt;
                    line-height: 1.1;
                }
                
                th {
                    font-weight: bold;
                    background-color: #F4F6F8;
                    font-size: 7pt;
                    padding: 3px;
                }
                
                /* Alle kolommen exact dezelfde breedte - 25% elk */
                .col-artikel, 
                .col-door, 
                .col-opmerking, 
                .col-antwoord { 
                    width: 25%; 
                }
                
                .col-artikel {
                    background-color: #F4F6F8;
                    font-weight: bold;
                }
                
            </style>
            
            <div class="header">';
            
            // Add logo if available
            if (!empty($logo_url)) {
                // Convert logo URL to base64 for better PDF compatibility
                $logo_data = $this->get_logo_as_base64($logo_url);
                if ($logo_data) {
                    $html .= '<div class="logo-container"><img src="' . $logo_data . '" /></div>';
                }
            }
            
            $html .= '
                <div class="title-container">
                    <h1>CVS samenvatting</h1>
                    <span class="date">' . $export_date . '</span>
                </div>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th class="col-artikel">Artikel</th>
                        <th class="col-door">Door</th>
                        <th class="col-opmerking">Opmerking</th>
                        <th class="col-antwoord">Antwoord</th>
                    </tr>
                </thead>
                <tbody>';
            
            // Add data rows
            foreach ($comments as $comment) {
                // Fix character encoding issues
                $post_title = $this->fix_text_encoding($comment->post_title);
                $author_name = $this->fix_text_encoding($comment->author_name);
                $comment_content = $this->fix_text_encoding($comment->comment_content);
                $admin_response = $this->fix_text_encoding($comment->admin_response ?: '-');
                
                $html .= '<tr>';
                $html .= '<td class="col-artikel">' . htmlspecialchars($post_title, ENT_QUOTES, 'UTF-8') . '</td>';
                $html .= '<td class="col-door">' . htmlspecialchars($author_name, ENT_QUOTES, 'UTF-8') . '</td>';
                $html .= '<td class="col-opmerking">' . nl2br(htmlspecialchars($comment_content, ENT_QUOTES, 'UTF-8')) . '</td>';
                $html .= '<td class="col-antwoord">' . nl2br(htmlspecialchars($admin_response, ENT_QUOTES, 'UTF-8')) . '</td>';
                $html .= '</tr>';
            }
            
            $html .= '
                </tbody>
            </table>';
            
            // Set footer for page numbering
            $mpdf->SetHTMLFooter('<div style="text-align: right; font-size: 9pt; color: #555;">Pagina {PAGENO}</div>');
            
            // Write HTML to PDF
            $mpdf->WriteHTML($html);
            
            // Generate filename with subdomain prefix if applicable
            $site_url = get_site_url();
            $parsed_url = parse_url($site_url);
            $host = isset($parsed_url['host']) ? $parsed_url['host'] : '';
            
            // Check if it's a subdomain (has more than one dot, excluding www)
            $host_without_www = preg_replace('/^www\./', '', $host);
            $dot_count = substr_count($host_without_www, '.');
            
            $filename_prefix = '';
            if ($dot_count > 0) {
                // Extract subdomain (everything before the first dot)
                $parts = explode('.', $host_without_www);
                if (count($parts) > 2 || ($dot_count == 1 && $parts[0] != 'www')) {
                    // It's a subdomain, use the first part
                    $filename_prefix = $parts[0] . '-';
                }
            }
            
            $filename = $filename_prefix . 'CVS samenvatting - ' . $export_date . '.pdf';
            
            // Output PDF as base64 for JavaScript download
            $pdf_content = $mpdf->Output('', 'S');
            $pdf_base64 = base64_encode($pdf_content);
            
            wp_send_json_success(array(
                'pdf' => $pdf_base64,
                'filename' => $filename,
                'count' => count($comments)
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => 'PDF generatie fout: ' . $e->getMessage()));
        }
    }
    
    /**
     * Fix text encoding issues for PDF export
     */
    private function fix_text_encoding($text) {
        if (empty($text)) {
            return '';
        }
        
        // Ensure UTF-8 encoding
        if (!mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'auto');
        }
        
        // Replace problematic characters with proper UTF-8 equivalents
        $replacements = array(
            // Smart quotes
            "\u201C" => '"',  // Left double quotation mark
            "\u201D" => '"',  // Right double quotation mark
            "\u2018" => "'",  // Left single quotation mark
            "\u2019" => "'",  // Right single quotation mark
            
            // En/em dashes
            "\u2013" => '-',  // En dash
            "\u2014" => '-',  // Em dash
            
            // Other common problematic characters
            "\u2026" => '...',  // Horizontal ellipsis
            "\u20AC" => 'EUR',  // Euro sign fallback
            "\u00AE" => '(R)',  // Registered trademark
            "\u00A9" => '(C)',  // Copyright
            "\u2122" => '(TM)', // Trademark
            
            // Remove or replace other non-printable characters
            "\x00" => '',  // Null bytes
            "\x01" => '',  // Start of heading
            "\x02" => '',  // Start of text
            "\x03" => '',  // End of text
            "\x04" => '',  // End of transmission
            "\x05" => '',  // Enquiry
            "\x06" => '',  // Acknowledge
            "\x07" => '',  // Bell
            "\x08" => '',  // Backspace
            "\x0B" => '',  // Vertical tab
            "\x0C" => '',  // Form feed
            "\x0E" => '',  // Shift out
            "\x0F" => '',  // Shift in
        );
        
        $text = str_replace(array_keys($replacements), array_values($replacements), $text);
        
        // Remove any remaining control characters except newlines and tabs
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);
        
        return $text;
    }
    
    /**
     * Get logo as base64 data URL
     */
    private function get_logo_as_base64($logo_url) {
        if (empty($logo_url)) {
            return false;
        }
        
        try {
            // Try to get image data
            $image_data = false;
            
            // Method 1: Try as WordPress upload
            $upload_dir = wp_upload_dir();
            $local_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $logo_url);
            
            if (file_exists($local_path) && is_readable($local_path)) {
                $image_data = @file_get_contents($local_path);
            }
            
            // Method 2: If it's an HTTP(S) URL from the same site
            if (!$image_data && preg_match('/^https?:\/\//i', $logo_url)) {
                $site_url = get_site_url();
                if (strpos($logo_url, $site_url) === 0) {
                    $relative_path = str_replace($site_url, '', $logo_url);
                    $possible_path = ABSPATH . ltrim($relative_path, '/');
                    
                    if (file_exists($possible_path) && is_readable($possible_path)) {
                        $image_data = @file_get_contents($possible_path);
                    }
                }
            }
            
            // Method 3: Try direct file_get_contents (for external URLs)
            if (!$image_data) {
                $image_data = @file_get_contents($logo_url);
            }
            
            if ($image_data === false) {
                return false;
            }
            
            // Get MIME type
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_buffer($finfo, $image_data);
            finfo_close($finfo);
            
            // Only process common image types
            if (!in_array($mime_type, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'])) {
                return false;
            }
            
            // Convert to base64 data URL
            $base64 = base64_encode($image_data);
            return "data:$mime_type;base64,$base64";
            
        } catch (Exception $e) {
            error_log('OVM: Logo conversion error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Convert array to CSV line
     */
    private function array_to_csv_line($array) {
        $line = '';
        foreach ($array as $value) {
            // Escape quotes and wrap in quotes if contains special characters
            $value = str_replace('"', '""', $value);
            if (strpos($value, ',') !== false || strpos($value, '"') !== false || strpos($value, "\n") !== false || strpos($value, "\r") !== false) {
                $value = '"' . $value . '"';
            }
            $line .= $value . ',';
        }
        return rtrim($line, ',') . "\r\n";
    }
    
    /**
     * Get status label
     */
    private function get_status_label($status) {
        $labels = array(
            'te_verwerken' => 'Te verwerken',
            'klaar_voor_export' => 'Klaar voor export',
            'afgerond' => 'Afgerond'
        );
        
        return isset($labels[$status]) ? $labels[$status] : $status;
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
    
    /**
     * Truncate text while preserving line breaks and formatting
     */
    private function truncate_with_formatting($text, $max_length = 200) {
        if (strlen($text) <= $max_length) {
            return $text;
        }
        
        // Cut at max_length
        $truncated = substr($text, 0, $max_length);
        
        // Try to cut at last complete word
        $last_space = strrpos($truncated, ' ');
        if ($last_space !== false && $last_space > $max_length * 0.8) {
            $truncated = substr($truncated, 0, $last_space);
        }
        
        return $truncated . '...';
    }
}