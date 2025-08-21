<?php
/**
 * Comment Tracker Class
 * 
 * Handles tracking of new WordPress comments
 */

if (!defined('ABSPATH')) {
    exit;
}

class OVM_Comment_Tracker {
    
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
     * Track new comment when posted
     */
    public function track_new_comment($comment_id, $comment_approved, $comment_data) {
        if ($this->data_manager->comment_exists($comment_id)) {
            return;
        }
        
        $this->save_comment_to_database($comment_id, $comment_data);
    }
    
    /**
     * Track inserted comment
     */
    public function track_inserted_comment($comment_id, $comment) {
        if ($this->data_manager->comment_exists($comment_id)) {
            return;
        }
        
        $comment_data = array(
            'comment_post_ID' => $comment->comment_post_ID,
            'comment_author' => $comment->comment_author,
            'comment_author_email' => $comment->comment_author_email,
            'comment_author_IP' => $comment->comment_author_IP,
            'comment_content' => $comment->comment_content,
            'comment_date' => $comment->comment_date
        );
        
        $this->save_comment_to_database($comment_id, $comment_data);
    }
    
    /**
     * Save comment to database
     */
    private function save_comment_to_database($comment_id, $comment_data) {
        $post_id = isset($comment_data['comment_post_ID']) ? $comment_data['comment_post_ID'] : 0;
        
        if (!$post_id) {
            return false;
        }
        
        $post = get_post($post_id);
        
        if (!$post) {
            return false;
        }
        
        $rating = $this->extract_rating($comment_id, $comment_data);
        
        $metadata = array(
            'original_status' => isset($comment_data['comment_approved']) ? $comment_data['comment_approved'] : '',
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '',
            'referrer' => isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : ''
        );
        
        $data = array(
            'comment_id' => $comment_id,
            'post_id' => $post_id,
            'post_title' => $post->post_title,
            'author_name' => isset($comment_data['comment_author']) ? $comment_data['comment_author'] : '',
            'author_email' => isset($comment_data['comment_author_email']) ? $comment_data['comment_author_email'] : '',
            'author_ip' => isset($comment_data['comment_author_IP']) ? $comment_data['comment_author_IP'] : '',
            'comment_content' => isset($comment_data['comment_content']) ? $comment_data['comment_content'] : '',
            'comment_date' => isset($comment_data['comment_date']) ? $comment_data['comment_date'] : current_time('mysql'),
            'rating' => $rating,
            'admin_response' => '',
            'status' => 'te_verwerken',
            'status_changed_date' => current_time('mysql'),
            'metadata' => $metadata
        );
        
        return $this->data_manager->insert_comment($data);
    }
    
    /**
     * Extract rating from comment if available
     */
    private function extract_rating($comment_id, $comment_data) {
        $rating = get_comment_meta($comment_id, 'rating', true);
        
        if ($rating) {
            return intval($rating);
        }
        
        if (isset($comment_data['comment_meta']) && isset($comment_data['comment_meta']['rating'])) {
            return intval($comment_data['comment_meta']['rating']);
        }
        
        $rating = apply_filters('ovm_extract_comment_rating', null, $comment_id, $comment_data);
        
        return $rating ? intval($rating) : null;
    }
}