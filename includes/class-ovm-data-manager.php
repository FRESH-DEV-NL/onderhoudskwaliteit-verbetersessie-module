<?php
/**
 * Data Manager Class
 * 
 * Handles all database operations for the plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class OVM_Data_Manager {
    
    /**
     * Instance of this class
     */
    private static $instance = null;
    
    /**
     * Database table name
     */
    private $table_name;
    
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
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'ovm_comments';
        
        // Ensure table exists on every load
        if (!$this->table_exists()) {
            $this->create_database_table();
        }
    }
    
    /**
     * Check if table exists
     */
    public function table_exists() {
        global $wpdb;
        
        $table_name = $wpdb->get_var(
            $wpdb->prepare(
                "SHOW TABLES LIKE %s",
                $this->table_name
            )
        );
        
        return $table_name === $this->table_name;
    }
    
    /**
     * Create database table
     */
    public function create_database_table() {
        global $wpdb;
        
        try {
            $charset_collate = $wpdb->get_charset_collate();
            
            // Check if images column exists, if not add it
            $columns = $wpdb->get_results("SHOW COLUMNS FROM {$this->table_name}");
            $column_names = array();
            if ($columns) {
                foreach ($columns as $column) {
                    $column_names[] = $column->Field;
                }
            }
            
            // Add images column if it doesn't exist
            if (!in_array('images', $column_names) && $this->table_exists()) {
                $wpdb->query("ALTER TABLE {$this->table_name} ADD COLUMN images TEXT DEFAULT NULL AFTER metadata");
            }
            
            // Use proper SQL syntax for dbDelta
            $sql = "CREATE TABLE {$this->table_name} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                comment_id bigint(20) unsigned DEFAULT NULL,
                post_id bigint(20) unsigned NOT NULL,
                post_title text NOT NULL,
                author_name varchar(255) NOT NULL DEFAULT '',
                author_email varchar(100) NOT NULL DEFAULT '',
                author_ip varchar(100) NOT NULL DEFAULT '',
                comment_content longtext NOT NULL,
                comment_date datetime DEFAULT CURRENT_TIMESTAMP,
                rating int(11) DEFAULT NULL,
                admin_response longtext DEFAULT NULL,
                status varchar(50) NOT NULL DEFAULT 'te_verwerken',
                status_changed_date datetime DEFAULT NULL,
                metadata longtext DEFAULT NULL,
                images text DEFAULT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY comment_id (comment_id),
                KEY post_id (post_id),
                KEY status (status),
                KEY comment_date (comment_date)
            ) {$charset_collate};";
            
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            $result = dbDelta($sql);
            
            // Log any errors
            if ($wpdb->last_error) {
                error_log('OVM Database Error: ' . $wpdb->last_error);
                return false;
            }
            
            // Update plugin version
            update_option('ovm_db_version', OVM_VERSION);
            
            return true;
            
        } catch (Exception $e) {
            error_log('OVM Exception: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Insert a new comment record
     */
    public function insert_comment($data) {
        global $wpdb;
        
        $defaults = array(
            'comment_id' => null,
            'post_id' => 0,
            'post_title' => '',
            'author_name' => '',
            'author_email' => '',
            'author_ip' => '',
            'comment_content' => '',
            'comment_date' => current_time('mysql'),
            'rating' => null,
            'admin_response' => null,
            'status' => 'te_verwerken',
            'status_changed_date' => current_time('mysql'),
            'metadata' => null,
            'images' => null
        );
        
        $data = wp_parse_args($data, $defaults);
        
        if (!empty($data['metadata']) && is_array($data['metadata'])) {
            $data['metadata'] = maybe_serialize($data['metadata']);
        }
        
        // Only detect images if not already provided
        if (empty($data['images'])) {
            // Detect images in comment content before stripping
            $detected_images = $this->detect_images_in_content($data['comment_content'], $data['comment_id']);
            if (!empty($detected_images)) {
                $data['images'] = json_encode($detected_images);
            }
        }
        
        $data['comment_content'] = $this->strip_content($data['comment_content']);
        
        $result = $wpdb->insert(
            $this->table_name,
            $data,
            array(
                '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s'
            )
        );
        
        return $result !== false ? $wpdb->insert_id : false;
    }
    
    /**
     * Detect images in comment content
     */
    private function detect_images_in_content($content, $comment_id = null) {
        $images = array();
        
        // Detect IMG tags in content
        preg_match_all('/<img[^>]+src=[\'"]([^\'"]+)[\'"][^>]*>/i', $content, $matches);
        if (!empty($matches[1])) {
            foreach ($matches[1] as $img_url) {
                $images[] = array(
                    'url' => esc_url_raw($img_url),
                    'type' => 'embedded'
                );
            }
        }
        
        // Check for WordPress comment attachments if comment_id is provided
        if ($comment_id) {
            // Check multiple possible meta keys for attachments
            $meta_keys = array('comment_attachment', 'dco_attachment_id', 'attachment_id');
            foreach ($meta_keys as $meta_key) {
                $attachments = get_comment_meta($comment_id, $meta_key, false);
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
        }
        
        return $images;
    }
    
    /**
     * Strip unnecessary whitespace and special characters from content
     * Less aggressive version that preserves formatting
     */
    private function strip_content($content) {
        // Remove HTML tags but preserve line breaks
        $content = preg_replace('/<br\s*\/?>/i', "\n", $content);
        $content = preg_replace('/<\/p>/i', "\n", $content);
        $content = preg_replace('/<p[^>]*>/i', '', $content);
        $content = wp_strip_all_tags($content);
        
        // Normalize line endings to \n
        $content = str_replace(array("\r\n", "\r"), "\n", $content);
        
        // Replace multiple consecutive newlines (3 or more) with maximum 2 newlines
        $content = preg_replace("/\n{3,}/", "\n\n", $content);
        
        // Remove leading/trailing whitespace per line but preserve line breaks
        $lines = explode("\n", $content);
        $lines = array_map('trim', $lines);
        $content = implode("\n", $lines);
        
        // Remove excessive spaces within lines (more than 2 becomes 1), but not newlines
        $lines = explode("\n", $content);
        foreach ($lines as &$line) {
            $line = preg_replace('/[ \t]{2,}/', ' ', $line);
        }
        $content = implode("\n", $lines);
        
        // Preserve list formatting (bullets at start of line)
        $content = preg_replace('/\n\s*[-–*•]\s+/', "\n– ", $content);
        
        // Final trim to remove leading/trailing whitespace
        $content = trim($content);
        
        return $content;
    }
    
    /**
     * Get comments by status
     */
    public function get_comments_by_status($status = 'te_verwerken', $page_id = null) {
        global $wpdb;
        
        $where = $wpdb->prepare("WHERE status = %s", $status);
        
        if ($page_id) {
            $where .= $wpdb->prepare(" AND post_id = %d", $page_id);
        }
        
        $query = "SELECT * FROM {$this->table_name} {$where} ORDER BY comment_date DESC";
        
        return $wpdb->get_results($query);
    }
    
    /**
     * Update comment
     */
    public function update_comment($id, $data) {
        global $wpdb;
        
        if (isset($data['metadata']) && is_array($data['metadata'])) {
            $data['metadata'] = maybe_serialize($data['metadata']);
        }
        
        if (isset($data['status'])) {
            $data['status_changed_date'] = current_time('mysql');
        }
        
        return $wpdb->update(
            $this->table_name,
            $data,
            array('id' => $id),
            null,
            array('%d')
        );
    }
    
    /**
     * Delete comment
     */
    public function delete_comment($id) {
        global $wpdb;
        
        return $wpdb->delete(
            $this->table_name,
            array('id' => $id),
            array('%d')
        );
    }
    
    /**
     * Get single comment
     */
    public function get_comment($id) {
        global $wpdb;
        
        $query = $wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $id
        );
        
        return $wpdb->get_row($query);
    }
    
    /**
     * Get all unique posts with comments
     */
    public function get_posts_with_comments($status = null) {
        global $wpdb;
        
        if ($status) {
            $query = $wpdb->prepare(
                "SELECT DISTINCT post_id, post_title FROM {$this->table_name} WHERE status = %s ORDER BY post_title ASC",
                $status
            );
        } else {
            $query = "SELECT DISTINCT post_id, post_title FROM {$this->table_name} ORDER BY post_title ASC";
        }
        
        return $wpdb->get_results($query);
    }
    
    /**
     * Bulk update status
     */
    public function bulk_update_status($ids, $new_status) {
        global $wpdb;
        
        if (empty($ids) || !is_array($ids)) {
            return false;
        }
        
        $ids_string = implode(',', array_map('intval', $ids));
        $status_changed_date = current_time('mysql');
        
        $query = $wpdb->prepare(
            "UPDATE {$this->table_name} 
             SET status = %s, status_changed_date = %s 
             WHERE id IN ({$ids_string})",
            $new_status,
            $status_changed_date
        );
        
        return $wpdb->query($query);
    }
    
    /**
     * Check if comment already exists
     */
    public function comment_exists($comment_id) {
        global $wpdb;
        
        $query = $wpdb->prepare(
            "SELECT id FROM {$this->table_name} WHERE comment_id = %d",
            $comment_id
        );
        
        return $wpdb->get_var($query) !== null;
    }
    
    /**
     * Import existing WordPress comments
     */
    public function import_existing_comments() {
        $comments = get_comments(array(
            'status' => 'approve',
            'number' => 0 // All comments
        ));
        
        $imported = 0;
        
        foreach ($comments as $comment) {
            // Check if comment already exists
            if ($this->comment_exists($comment->comment_ID)) {
                continue;
            }
            
            $post = get_post($comment->comment_post_ID);
            if (!$post) {
                continue;
            }
            
            // Extract rating if available
            $rating = get_comment_meta($comment->comment_ID, 'rating', true);
            $rating = $rating ? intval($rating) : null;
            
            $metadata = array(
                'imported_on_activation' => current_time('mysql'),
                'original_status' => $comment->comment_approved,
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
            
            if ($this->insert_comment($data)) {
                $imported++;
            }
        }
        
        // Set flag that import has been done
        update_option('ovm_comments_imported', true);
        update_option('ovm_import_count', $imported);
        
        return $imported;
    }
    
    /**
     * Update existing comments with missing images
     */
    public function update_missing_images() {
        global $wpdb;
        
        // Get all comments without images
        $comments = $wpdb->get_results("SELECT * FROM {$this->table_name} WHERE comment_id IS NOT NULL");
        
        $updated = 0;
        foreach ($comments as $comment) {
            $images = array();
            
            // Check for IMG tags in content
            $original_comment = get_comment($comment->comment_id);
            if ($original_comment) {
                // Check original content for images
                preg_match_all('/<img[^>]+src=[\'"]([^\'"]+)[\'"][^>]*>/i', $original_comment->comment_content, $matches);
                if (!empty($matches[1])) {
                    foreach ($matches[1] as $img_url) {
                        $images[] = array(
                            'url' => esc_url_raw($img_url),
                            'type' => 'embedded'
                        );
                    }
                }
                
                // Debug: Get all meta keys for this comment
                $all_meta = get_comment_meta($comment->comment_id);
                error_log('Comment ID ' . $comment->comment_id . ' has meta keys: ' . print_r(array_keys($all_meta), true));
                
                // Check for WordPress comment attachments (multiple possible meta keys)
                $meta_keys = array('comment_attachment', 'dco_attachment_id', 'attachment_id');
                foreach ($meta_keys as $meta_key) {
                    $attachments = get_comment_meta($comment->comment_id, $meta_key, false);
                    if (!empty($attachments)) {
                        error_log('Found attachments for meta key ' . $meta_key . ': ' . print_r($attachments, true));
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
                                    error_log('Added image from attachment ID ' . $attachment_id . ': ' . ($full_url ?: $attachment_url));
                                }
                            }
                        }
                    }
                }
            }
            
            // Update if images found
            if (!empty($images)) {
                $this->update_comment($comment->id, array(
                    'images' => json_encode($images)
                ));
                $updated++;
            }
        }
        
        return $updated;
    }
    
    /**
     * Clean old data
     */
    public function clean_old_data($months = 12) {
        global $wpdb;
        
        $date_threshold = date('Y-m-d H:i:s', strtotime("-{$months} months"));
        
        $query = $wpdb->prepare(
            "DELETE FROM {$this->table_name} 
             WHERE status = 'afgerond' AND status_changed_date < %s",
            $date_threshold
        );
        
        return $wpdb->query($query);
    }
}