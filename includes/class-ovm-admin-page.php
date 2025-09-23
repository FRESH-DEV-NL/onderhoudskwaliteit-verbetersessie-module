<?php
/**
 * Admin Page Class
 * 
 * Handles the admin interface and page rendering
 */

if (!defined('ABSPATH')) {
    exit;
}

class OVM_Admin_Page {
    
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
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Verbetersessie Module', 'onderhoudskwaliteit-verbetersessie'),
            __('Verbetersessie Module', 'onderhoudskwaliteit-verbetersessie'),
            'manage_options',
            'ovm-settings',
            array($this, 'render_admin_page'),
            'dashicons-feedback',
            26
        );
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        if ('toplevel_page_ovm-settings' !== $hook) {
            return;
        }
        
        // Enqueue WordPress media scripts for logo upload
        wp_enqueue_media();
        
        wp_enqueue_style(
            'ovm-admin',
            OVM_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            OVM_VERSION
        );
        
        // Enqueue jQuery UI for column sorting
        wp_enqueue_script('jquery-ui-sortable');
        
        wp_enqueue_script(
            'ovm-admin',
            OVM_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery', 'jquery-ui-sortable'),
            OVM_VERSION,
            true
        );
        
        wp_localize_script('ovm-admin', 'ovm_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ovm_ajax_nonce'),
            'strings' => array(
                'confirm_delete' => __('Weet je zeker dat je deze opmerking wilt verwijderen?', 'onderhoudskwaliteit-verbetersessie'),
                'confirm_bulk_delete' => __('Weet je zeker dat je de geselecteerde opmerkingen wilt verwijderen?', 'onderhoudskwaliteit-verbetersessie'),
                'saving' => __('Opslaan...', 'onderhoudskwaliteit-verbetersessie'),
                'saved' => __('Opgeslagen', 'onderhoudskwaliteit-verbetersessie'),
                'error' => __('Er is een fout opgetreden', 'onderhoudskwaliteit-verbetersessie'),
                'no_response_required' => __('Een reactie is vereist voordat je naar "Klaar voor export" kunt verplaatsen', 'onderhoudskwaliteit-verbetersessie')
            )
        ));
    }
    
    /**
     * Render admin page
     */
    public function render_admin_page() {
        $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'te_verwerken';
        $orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'datum';
        $order = isset($_GET['order']) ? sanitize_text_field($_GET['order']) : 'desc';
        
        ?>
        <div class="wrap ovm-admin-wrap">
            <h1><?php echo esc_html__('Verbetersessie Module', 'onderhoudskwaliteit-verbetersessie'); ?></h1>
            
            <nav class="nav-tab-wrapper">
                <a href="?page=ovm-settings&tab=te_verwerken" 
                   class="nav-tab <?php echo $current_tab === 'te_verwerken' ? 'nav-tab-active' : ''; ?>">
                    <?php echo esc_html__('Te verwerken', 'onderhoudskwaliteit-verbetersessie'); ?>
                    <?php $this->render_count_badge('te_verwerken'); ?>
                </a>
                <a href="?page=ovm-settings&tab=klaar_voor_export" 
                   class="nav-tab <?php echo $current_tab === 'klaar_voor_export' ? 'nav-tab-active' : ''; ?>">
                    <?php echo esc_html__('Klaar voor export', 'onderhoudskwaliteit-verbetersessie'); ?>
                    <?php $this->render_count_badge('klaar_voor_export'); ?>
                </a>
                <a href="?page=ovm-settings&tab=afgerond" 
                   class="nav-tab <?php echo $current_tab === 'afgerond' ? 'nav-tab-active' : ''; ?>">
                    <?php echo esc_html__('Afgerond', 'onderhoudskwaliteit-verbetersessie'); ?>
                    <?php $this->render_count_badge('afgerond'); ?>
                </a>
                <a href="?page=ovm-settings&tab=instellingen" 
                   class="nav-tab <?php echo $current_tab === 'instellingen' ? 'nav-tab-active' : ''; ?>">
                    <?php echo esc_html__('Instellingen', 'onderhoudskwaliteit-verbetersessie'); ?>
                </a>
            </nav>
            
            <div class="ovm-tab-content" data-tab="<?php echo esc_attr($current_tab); ?>">
                <?php $this->render_tab_content($current_tab); ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render count badge
     */
    private function render_count_badge($status) {
        $comments = $this->data_manager->get_comments_by_status($status);
        $count = count($comments);
        
        if ($count > 0) {
            echo '<span class="ovm-count-badge">' . esc_html($count) . '</span>';
        }
    }
    
    /**
     * Render tab content
     */
    private function render_tab_content($tab) {
        if ($tab === 'instellingen') {
            $this->render_settings_tab();
            return;
        }
        
        ?>
        <div class="ovm-filters">
            <select id="ovm-page-filter" class="ovm-page-filter">
                <option value=""><?php echo esc_html__('Alle pagina\'s', 'onderhoudskwaliteit-verbetersessie'); ?></option>
                <?php
                $posts = $this->data_manager->get_posts_with_comments($tab);
                foreach ($posts as $post) {
                    echo '<option value="' . esc_attr($post->post_id) . '">' . 
                         esc_html($post->post_title) . '</option>';
                }
                ?>
            </select>
            
            <?php if ($tab === 'klaar_voor_export'): ?>
            <button class="button button-secondary ovm-export-btn" data-status="klaar_voor_export">
                <?php echo esc_html__('Export naar PDF', 'onderhoudskwaliteit-verbetersessie'); ?>
            </button>
            <?php endif; ?>
            
            <button class="button button-primary ovm-import-btn" id="ovm-import-comments">
                <span class="dashicons dashicons-download" style="margin-top: 3px;"></span>
                <?php echo esc_html__('Importeer Comments', 'onderhoudskwaliteit-verbetersessie'); ?>
            </button>
            
            <button class="button button-secondary ovm-update-images-btn" id="ovm-update-images">
                <span class="dashicons dashicons-format-image" style="margin-top: 3px;"></span>
                <?php echo esc_html__('Update Afbeeldingen', 'onderhoudskwaliteit-verbetersessie'); ?>
            </button>
        </div>
        
        <form id="ovm-bulk-form">
            <div class="tablenav top">
                <div class="alignleft actions bulkactions">
                    <select name="bulk_action" id="bulk-action-selector">
                        <option value=""><?php echo esc_html__('Bulk acties', 'onderhoudskwaliteit-verbetersessie'); ?></option>
                        <?php $this->render_bulk_actions($tab); ?>
                    </select>
                    <button type="submit" class="button action"><?php echo esc_html__('Toepassen', 'onderhoudskwaliteit-verbetersessie'); ?></button>
                </div>
            </div>
            
            <table class="wp-list-table widefat fixed striped ovm-comments-table">
                <thead>
                    <tr>
                        <td class="manage-column column-cb check-column">
                            <input type="checkbox" id="cb-select-all-1">
                        </td>
                        <?php 
                        // Get custom column order
                        $column_order = get_option('ovm_column_order', array('artikel', 'datum', 'door', 'opmerking', 'reactie'));
                        $column_labels = array(
                            'artikel' => __('Artikel', 'onderhoudskwaliteit-verbetersessie'),
                            'datum' => __('Datum', 'onderhoudskwaliteit-verbetersessie'),
                            'door' => __('Door', 'onderhoudskwaliteit-verbetersessie'),
                            'opmerking' => __('Opmerking', 'onderhoudskwaliteit-verbetersessie'),
                            'reactie' => __('Reactie', 'onderhoudskwaliteit-verbetersessie')
                        );
                        
                        // Get sorting parameters from URL
                        $current_orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'datum';
                        $current_order = isset($_GET['order']) ? sanitize_text_field($_GET['order']) : 'desc';
                        
                        // Define sortable columns
                        $sortable_columns = array('artikel', 'datum', 'door', 'opmerking', 'reactie');
                        
                        foreach ($column_order as $column_key) {
                            if (isset($column_labels[$column_key])) {
                                if (in_array($column_key, $sortable_columns)) {
                                    // Determine the sorting class and next order
                                    $sort_class = '';
                                    $next_order = 'asc';
                                    if ($current_orderby === $column_key) {
                                        $sort_class = 'sorted ' . $current_order;
                                        $next_order = ($current_order === 'asc') ? 'desc' : 'asc';
                                    } else {
                                        $sort_class = 'sortable';
                                    }
                                    
                                    // Build the sorting URL - use $tab instead of $current_tab
                                    $sort_url = add_query_arg(array(
                                        'page' => 'ovm-settings',
                                        'tab' => $tab,
                                        'orderby' => $column_key,
                                        'order' => $next_order
                                    ), admin_url('admin.php'));
                                    
                                    echo '<th scope="col" class="manage-column column-' . esc_attr($column_key) . ' ' . esc_attr($sort_class) . '" data-column="' . esc_attr($column_key) . '">';
                                    echo '<a href="' . esc_url($sort_url) . '">';
                                    echo '<span>' . esc_html($column_labels[$column_key]) . '</span>';
                                    echo '<span class="sorting-indicators">';
                                    echo '<span class="sorting-indicator asc" aria-hidden="true"></span>';
                                    echo '<span class="sorting-indicator desc" aria-hidden="true"></span>';
                                    echo '</span>';
                                    echo '</a>';
                                    echo '</th>';
                                } else {
                                    echo '<th scope="col" class="manage-column" data-column="' . esc_attr($column_key) . '">' . 
                                         esc_html($column_labels[$column_key]) . '</th>';
                                }
                            }
                        }
                        ?>
                        <th scope="col" class="manage-column"><?php echo esc_html__('Acties', 'onderhoudskwaliteit-verbetersessie'); ?></th>
                    </tr>
                </thead>
                <tbody id="ovm-comments-list">
                    <?php $this->render_comments_rows($tab); ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td class="manage-column column-cb check-column">
                            <input type="checkbox" id="cb-select-all-2">
                        </td>
                        <?php 
                        // Use same column order as thead
                        foreach ($column_order as $column_key) {
                            if (isset($column_labels[$column_key])) {
                                echo '<th scope="col" class="manage-column" data-column="' . esc_attr($column_key) . '">' . 
                                     esc_html($column_labels[$column_key]) . '</th>';
                            }
                        }
                        ?>
                        <th scope="col" class="manage-column"><?php echo esc_html__('Acties', 'onderhoudskwaliteit-verbetersessie'); ?></th>
                    </tr>
                </tfoot>
            </table>
        </form>
        <?php
    }
    
    /**
     * Render bulk actions
     */
    private function render_bulk_actions($tab) {
        switch ($tab) {
            case 'te_verwerken':
                ?>
                <option value="move_to_export"><?php echo esc_html__('Verplaats naar "Klaar voor export"', 'onderhoudskwaliteit-verbetersessie'); ?></option>
                <option value="delete_wp_comments"><?php echo esc_html__('WordPress comments verwijderen', 'onderhoudskwaliteit-verbetersessie'); ?></option>
                <option value="delete"><?php echo esc_html__('Verwijderen', 'onderhoudskwaliteit-verbetersessie'); ?></option>
                <?php
                break;
                
            case 'klaar_voor_export':
                ?>
                <option value="move_to_completed"><?php echo esc_html__('Verplaats naar "Afgerond"', 'onderhoudskwaliteit-verbetersessie'); ?></option>
                <option value="move_to_processing"><?php echo esc_html__('Terugzetten naar "Te verwerken"', 'onderhoudskwaliteit-verbetersessie'); ?></option>
                <option value="delete"><?php echo esc_html__('Verwijderen', 'onderhoudskwaliteit-verbetersessie'); ?></option>
                <?php
                break;
                
            case 'afgerond':
                ?>
                <option value="move_to_export"><?php echo esc_html__('Terugzetten naar "Klaar voor export"', 'onderhoudskwaliteit-verbetersessie'); ?></option>
                <option value="delete_wp_comments"><?php echo esc_html__('WordPress comments verwijderen', 'onderhoudskwaliteit-verbetersessie'); ?></option>
                <option value="delete"><?php echo esc_html__('Verwijderen', 'onderhoudskwaliteit-verbetersessie'); ?></option>
                <?php
                break;
        }
    }
    
    /**
     * Render comments rows
     */
    private function render_comments_rows($status, $page_id = null) {
        // Get sorting parameters
        $orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'datum';
        $order = isset($_GET['order']) ? sanitize_text_field($_GET['order']) : 'desc';
        
        $comments = $this->data_manager->get_comments_by_status($status, $page_id, $orderby, $order);
        
        if (empty($comments)) {
            ?>
            <tr>
                <td colspan="7" class="no-items">
                    <?php echo esc_html__('Geen opmerkingen gevonden', 'onderhoudskwaliteit-verbetersessie'); ?>
                </td>
            </tr>
            <?php
            return;
        }
        
        foreach ($comments as $comment) {
            $this->render_single_comment_row($comment, $status);
        }
    }
    
    /**
     * Truncate text while preserving line breaks
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
    
    /**
     * Fix text encoding issues for display
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
        );
        
        $text = str_replace(array_keys($replacements), array_values($replacements), $text);
        
        // Remove any remaining control characters except newlines and tabs
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);
        
        return $text;
    }
    
    /**
     * Render single comment row
     */
    private function render_single_comment_row($comment, $status) {
        $post_link = get_permalink($comment->post_id);
        $metadata = maybe_unserialize($comment->metadata);
        
        // Fix text encoding for all content
        $comment->post_title = $this->fix_text_encoding($comment->post_title);
        $comment->author_name = $this->fix_text_encoding($comment->author_name);
        $comment->comment_content = $this->fix_text_encoding($comment->comment_content);
        $comment->admin_response = $this->fix_text_encoding($comment->admin_response);
        
        $truncated_content = $this->truncate_with_formatting($comment->comment_content, 200);
        $full_content_class = strlen($comment->comment_content) > 200 ? 'has-more' : '';
        
        // Get column order
        $column_order = get_option('ovm_column_order', array('artikel', 'datum', 'door', 'opmerking', 'reactie'));
        
        // Check if item is flagged
        $flagged_class = (!empty($comment->flagged) && $comment->flagged == 1) ? 'ovm-flagged-row' : '';
        
        ?>
        <tr data-comment-id="<?php echo esc_attr($comment->id); ?>" class="<?php echo esc_attr($flagged_class); ?>">
            <th scope="row" class="check-column">
                <input type="checkbox" name="comment_ids[]" value="<?php echo esc_attr($comment->id); ?>">
            </th>
            <?php
            // Render all columns in the custom order
            foreach ($column_order as $column_key) {
                switch ($column_key) {
                    case 'artikel':
                        ?>
                        <td data-column="artikel">
                            <a href="<?php echo esc_url($post_link); ?>" target="_blank">
                                <?php echo esc_html($comment->post_title); ?>
                            </a>
                        </td>
                        <?php
                        break;
                        
                    case 'datum':
                        ?>
                        <td data-column="datum">
                            <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($comment->comment_date))); ?>
                        </td>
                        <?php
                        break;
                        
                    case 'door':
                        ?>
                        <td data-column="door">
                            <span class="ovm-author-name" title="<?php echo esc_attr($comment->author_email); ?>">
                                <?php echo esc_html($comment->author_name); ?>
                            </span>
                            <?php if ($comment->rating): ?>
                                <span class="ovm-rating">
                                    <?php echo str_repeat('★', $comment->rating) . str_repeat('☆', 5 - $comment->rating); ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <?php
                        break;
                        
                    case 'opmerking':
                        ?>
            <td data-column="opmerking" class="ovm-comment-content <?php echo esc_attr($full_content_class); ?>">
                <div class="ovm-content-display">
                    <div class="ovm-content-truncated">
                        <?php echo nl2br(esc_html($truncated_content)); ?>
                    </div>
                    <?php if ($full_content_class): ?>
                    <div class="ovm-content-full" style="display: none;">
                        <?php echo nl2br(esc_html($comment->comment_content)); ?>
                    </div>
                    <a href="#" class="ovm-toggle-content"><?php echo esc_html__('Meer', 'onderhoudskwaliteit-verbetersessie'); ?></a>
                    <?php endif; ?>
                </div>
                
                <?php 
                // Display images if available
                if (!empty($comment->images)) {
                    $images = json_decode($comment->images, true);
                    if (!empty($images) && is_array($images)): ?>
                    <div class="ovm-images-container">
                        <strong><?php echo esc_html__('Afbeeldingen:', 'onderhoudskwaliteit-verbetersessie'); ?></strong>
                        <div class="ovm-images-grid">
                            <?php foreach ($images as $image): ?>
                            <div class="ovm-image-item">
                                <img src="<?php echo esc_url($image['url']); ?>" alt="Comment image">
                                <div class="ovm-image-actions">
                                    <a href="<?php echo esc_url($image['url']); ?>" download target="_blank">⬇️ Download</a>
                                    <a href="<?php echo esc_url($image['url']); ?>" class="ovm-view-image">🔍 Bekijk</a>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif;
                }
                ?>
                
                <?php if ($status === 'te_verwerken'): ?>
                <div class="ovm-content-edit" style="display: none;">
                    <textarea class="ovm-comment-edit" 
                              data-comment-id="<?php echo esc_attr($comment->id); ?>"
                              rows="4"><?php echo esc_textarea($comment->comment_content); ?></textarea>
                    <div class="ovm-edit-actions">
                        <button type="button" class="button button-small ovm-save-comment"><?php echo esc_html__('Opslaan', 'onderhoudskwaliteit-verbetersessie'); ?></button>
                        <button type="button" class="button button-small ovm-cancel-edit"><?php echo esc_html__('Annuleren', 'onderhoudskwaliteit-verbetersessie'); ?></button>
                    </div>
                    <span class="ovm-comment-save-indicator"></span>
                </div>
                
                <div class="ovm-edit-controls">
                    <button type="button" class="button button-small button-link ovm-edit-comment">
                        <?php echo esc_html__('Bewerken', 'onderhoudskwaliteit-verbetersessie'); ?>
                    </button>
                </div>
                <?php endif; ?>
            </td>
                        <?php
                        break;
                        
                    case 'reactie':
                        ?>
            <td data-column="reactie">
                <?php if ($status === 'te_verwerken'): ?>
                    <textarea class="ovm-admin-response" 
                              data-comment-id="<?php echo esc_attr($comment->id); ?>"
                              placeholder="<?php echo esc_attr__('Typ je reactie...', 'onderhoudskwaliteit-verbetersessie'); ?>"
                              rows="3"><?php echo esc_textarea($comment->admin_response); ?></textarea>
                    <span class="ovm-save-indicator"></span>
                <?php else: ?>
                    <div class="ovm-admin-response-readonly">
                        <?php if (!empty($comment->admin_response)): ?>
                            <?php echo nl2br(esc_html($comment->admin_response)); ?>
                        <?php else: ?>
                            <em><?php echo esc_html__('Geen reactie', 'onderhoudskwaliteit-verbetersessie'); ?></em>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </td>
                        <?php
                        break;
                }
            }
            ?>
            <td>
                <?php $this->render_action_buttons($comment->id, $status, $comment); ?>
            </td>
        </tr>
        <?php
    }
    
    /**
     * Render action buttons
     */
    private function render_action_buttons($comment_id, $status, $comment = null) {
        ?>
        <div class="ovm-actions">
            <?php
            switch ($status) {
                case 'te_verwerken':
                    ?>
                    <button type="button" class="button button-small ovm-action-btn" 
                            data-action="move_to_export" 
                            data-comment-id="<?php echo esc_attr($comment_id); ?>">
                        <?php echo esc_html__('→ Export', 'onderhoudskwaliteit-verbetersessie'); ?>
                    </button>
                    <button type="button" class="button button-small button-link-delete ovm-action-btn" 
                            data-action="delete_wp_comment" 
                            data-comment-id="<?php echo esc_attr($comment_id); ?>"
                            onclick="return confirm('Weet je zeker dat je de WordPress comment wilt verwijderen? Dit kan niet ongedaan worden gemaakt.');">
                        <?php echo esc_html__('WP Comment Verwijderen', 'onderhoudskwaliteit-verbetersessie'); ?>
                    </button>
                    <?php
                    break;
                    
                case 'klaar_voor_export':
                    ?>
                    <button type="button" class="button button-small ovm-action-btn" 
                            data-action="move_to_completed" 
                            data-comment-id="<?php echo esc_attr($comment_id); ?>">
                        <?php echo esc_html__('→ Afgerond', 'onderhoudskwaliteit-verbetersessie'); ?>
                    </button>
                    <button type="button" class="button button-small ovm-action-btn" 
                            data-action="move_to_processing" 
                            data-comment-id="<?php echo esc_attr($comment_id); ?>">
                        <?php echo esc_html__('← Terug', 'onderhoudskwaliteit-verbetersessie'); ?>
                    </button>
                    <?php
                    break;
                    
                case 'afgerond':
                    ?>
                    <button type="button" class="button button-small ovm-action-btn" 
                            data-action="move_to_export" 
                            data-comment-id="<?php echo esc_attr($comment_id); ?>">
                        <?php echo esc_html__('← Export', 'onderhoudskwaliteit-verbetersessie'); ?>
                    </button>
                    <button type="button" class="button button-small button-link-delete ovm-action-btn" 
                            data-action="delete_wp_comment" 
                            data-comment-id="<?php echo esc_attr($comment_id); ?>"
                            onclick="return confirm('Weet je zeker dat je de WordPress comment wilt verwijderen? Dit kan niet ongedaan worden gemaakt.');">
                        <?php echo esc_html__('WP Comment Verwijderen', 'onderhoudskwaliteit-verbetersessie'); ?>
                    </button>
                    <?php
                    break;
            }
            ?>
            <?php if ($status === 'te_verwerken'): ?>
            <button type="button" class="button button-small ovm-chatgpt-btn" 
                    data-comment-id="<?php echo esc_attr($comment_id); ?>">
                💬 ChatGPT
            </button>
            <?php endif; ?>
            <?php if (($status === 'te_verwerken' || $status === 'klaar_voor_export') && $comment): ?>
            <button type="button" class="button button-small ovm-flag-btn <?php echo (!empty($comment->flagged) && $comment->flagged == 1) ? 'is-flagged' : ''; ?>" 
                    data-comment-id="<?php echo esc_attr($comment_id); ?>"
                    data-action="toggle_flag"
                    title="<?php echo esc_attr__('Markeer dit item', 'onderhoudskwaliteit-verbetersessie'); ?>">
                <span class="flag-icon"><?php echo (!empty($comment->flagged) && $comment->flagged == 1) ? '🚩' : '⚑'; ?></span>
            </button>
            <?php endif; ?>
            <button type="button" class="button button-small button-link-delete ovm-delete-btn" 
                    data-comment-id="<?php echo esc_attr($comment_id); ?>">
                <?php echo esc_html__('Verwijderen', 'onderhoudskwaliteit-verbetersessie'); ?>
            </button>
        </div>
        <?php
    }
    
    /**
     * Render settings tab
     */
    private function render_settings_tab() {
        // Handle form submission FIRST
        if (isset($_POST['save_settings']) && wp_verify_nonce($_POST['ovm_settings_nonce'], 'ovm_save_settings')) {
            update_option('ovm_chatgpt_api_key', sanitize_text_field($_POST['chatgpt_api_key']));
            update_option('ovm_chatgpt_prompt', sanitize_textarea_field($_POST['chatgpt_prompt']));
            update_option('ovm_logo_url', esc_url_raw($_POST['ovm_logo_url']));
            
            // Save column order
            if (isset($_POST['ovm_column_order'])) {
                $column_order = explode(',', sanitize_text_field($_POST['ovm_column_order']));
                // Validate that all columns are present
                $valid_columns = array('artikel', 'datum', 'door', 'opmerking', 'reactie');
                $filtered_order = array_intersect($column_order, $valid_columns);
                if (count($filtered_order) === 5) {
                    update_option('ovm_column_order', $filtered_order);
                }
            }
            
            echo '<div class="notice notice-success is-dismissible"><p>' . 
                 esc_html__('Instellingen opgeslagen!', 'onderhoudskwaliteit-verbetersessie') . 
                 '</p></div>';
        }
        
        // NOW get the current values (after potential save)
        $chatgpt_api_key = get_option('ovm_chatgpt_api_key', '');
        $chatgpt_prompt = get_option('ovm_chatgpt_prompt', 'Herschrijf deze tekst maar behoud de toon en zorg dat er geen spelfouten in de tekst zit: [reactie_tekst]');
        $logo_url = get_option('ovm_logo_url', '');
        
        // Get column order settings with default order
        $default_column_order = array('artikel', 'datum', 'door', 'opmerking', 'reactie');
        $column_order = get_option('ovm_column_order', $default_column_order);
        ?>
        <div class="ovm-settings-container">
            <h3><?php echo esc_html__('Export Instellingen', 'onderhoudskwaliteit-verbetersessie'); ?></h3>
            
            <form id="ovm-settings-form" method="post">
                <?php wp_nonce_field('ovm_save_settings', 'ovm_settings_nonce'); ?>
                
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">
                            <label for="ovm_logo_url"><?php echo esc_html__('Bedrijfslogo URL', 'onderhoudskwaliteit-verbetersessie'); ?></label>
                        </th>
                        <td>
                            <input type="url" 
                                   id="ovm_logo_url" 
                                   name="ovm_logo_url" 
                                   value="<?php echo esc_attr($logo_url); ?>" 
                                   class="regular-text" 
                                   placeholder="https://voorbeeld.nl/logo.png" />
                            <button type="button" class="button button-secondary" id="ovm_upload_logo">
                                <?php echo esc_html__('Upload Logo', 'onderhoudskwaliteit-verbetersessie'); ?>
                            </button>
                            <?php if ($logo_url) : ?>
                                <div style="margin-top: 10px;">
                                    <img src="<?php echo esc_url($logo_url); ?>" alt="Logo preview" style="max-height: 60px; max-width: 150px;" />
                                </div>
                            <?php endif; ?>
                            <p class="description">
                                <?php echo esc_html__('De URL van het bedrijfslogo dat in de PDF export wordt getoond.', 'onderhoudskwaliteit-verbetersessie'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <h3><?php echo esc_html__('Kolom Volgorde', 'onderhoudskwaliteit-verbetersessie'); ?></h3>
                
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">
                            <?php echo esc_html__('Kolom volgorde aanpassen', 'onderhoudskwaliteit-verbetersessie'); ?>
                        </th>
                        <td>
                            <div id="ovm-column-order-container" style="max-width: 400px;">
                                <p class="description" style="margin-bottom: 10px;">
                                    <?php echo esc_html__('Sleep de kolommen om de volgorde aan te passen. Checkbox en Acties staan vast.', 'onderhoudskwaliteit-verbetersessie'); ?>
                                </p>
                                <ul id="ovm-column-order-list" style="list-style: none; padding: 0; margin: 0;">
                                    <?php 
                                    $column_labels = array(
                                        'artikel' => __('Artikel', 'onderhoudskwaliteit-verbetersessie'),
                                        'datum' => __('Datum', 'onderhoudskwaliteit-verbetersessie'),
                                        'door' => __('Door', 'onderhoudskwaliteit-verbetersessie'),
                                        'opmerking' => __('Opmerking', 'onderhoudskwaliteit-verbetersessie'),
                                        'reactie' => __('Reactie', 'onderhoudskwaliteit-verbetersessie')
                                    );
                                    
                                    foreach ($column_order as $column_key): 
                                        if (isset($column_labels[$column_key])): ?>
                                        <li class="ovm-sortable-column" data-column="<?php echo esc_attr($column_key); ?>" 
                                            style="background: #f4f6f8; border: 1px solid #c9ccd1; padding: 8px 12px; margin-bottom: 5px; cursor: move; border-radius: 3px;">
                                            <span class="dashicons dashicons-move" style="margin-right: 8px; color: #666;"></span>
                                            <?php echo esc_html($column_labels[$column_key]); ?>
                                        </li>
                                        <?php endif; 
                                    endforeach; ?>
                                </ul>
                                <input type="hidden" id="ovm_column_order" name="ovm_column_order" 
                                       value="<?php echo esc_attr(implode(',', $column_order)); ?>" />
                            </div>
                        </td>
                    </tr>
                </table>
                
                <h3><?php echo esc_html__('ChatGPT Instellingen', 'onderhoudskwaliteit-verbetersessie'); ?></h3>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="chatgpt_api_key"><?php echo esc_html__('OpenAI API Key', 'onderhoudskwaliteit-verbetersessie'); ?></label>
                        </th>
                        <td>
                            <input type="password" 
                                   id="chatgpt_api_key" 
                                   name="chatgpt_api_key" 
                                   value="<?php echo esc_attr($chatgpt_api_key); ?>" 
                                   class="regular-text" 
                                   placeholder="sk-..." />
                            <p class="description"><?php echo esc_html__('Je OpenAI API key voor ChatGPT integratie', 'onderhoudskwaliteit-verbetersessie'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="chatgpt_prompt"><?php echo esc_html__('ChatGPT Prompt', 'onderhoudskwaliteit-verbetersessie'); ?></label>
                        </th>
                        <td>
                            <textarea id="chatgpt_prompt" 
                                      name="chatgpt_prompt" 
                                      rows="5" 
                                      class="large-text"><?php echo esc_textarea($chatgpt_prompt); ?></textarea>
                            <p class="description">
                                <?php echo esc_html__('Gebruik [reactie_tekst] als placeholder voor de opmerking tekst', 'onderhoudskwaliteit-verbetersessie'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <h3><?php echo esc_html__('Shortcode Instructies', 'onderhoudskwaliteit-verbetersessie'); ?></h3>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <?php echo esc_html__('Basis Shortcode', 'onderhoudskwaliteit-verbetersessie'); ?>
                        </th>
                        <td>
                            <code style="background: #f0f0f1; padding: 5px 10px; border-radius: 3px; font-size: 14px;">
                                [ovm_afgerond_tabel]
                            </code>
                            <p class="description">
                                <?php echo esc_html__('Plaats deze shortcode op een pagina of bericht om de tabel met afgeronde verbeteringen weer te geven.', 'onderhoudskwaliteit-verbetersessie'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <?php echo esc_html__('Shortcode Parameters', 'onderhoudskwaliteit-verbetersessie'); ?>
                        </th>
                        <td>
                            <div style="background: #f9f9f9; padding: 15px; border-radius: 5px; border-left: 3px solid #0073aa;">
                                <p><strong><?php echo esc_html__('Beschikbare parameters:', 'onderhoudskwaliteit-verbetersessie'); ?></strong></p>
                                <ul style="list-style: disc; margin-left: 20px;">
                                    <li>
                                        <code>titel="..."</code> - 
                                        <?php echo esc_html__('Aangepaste titel voor de tabel (standaard: "Afgeronde Verbeteringen")', 'onderhoudskwaliteit-verbetersessie'); ?>
                                    </li>
                                    <li>
                                        <code>toon_zoekbalk="ja/nee"</code> - 
                                        <?php echo esc_html__('Toon zoekbalk boven de tabel (standaard: "ja")', 'onderhoudskwaliteit-verbetersessie'); ?>
                                    </li>
                                    <li>
                                        <code>items_per_pagina="10"</code> - 
                                        <?php echo esc_html__('Aantal items per pagina (standaard: 10)', 'onderhoudskwaliteit-verbetersessie'); ?>
                                    </li>
                                    <li>
                                        <code>taal="nl/en"</code> - 
                                        <?php echo esc_html__('Taal voor DataTables interface (standaard: "nl")', 'onderhoudskwaliteit-verbetersessie'); ?>
                                    </li>
                                </ul>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <?php echo esc_html__('Voorbeelden', 'onderhoudskwaliteit-verbetersessie'); ?>
                        </th>
                        <td>
                            <div style="background: #f0f0f1; padding: 15px; border-radius: 5px; margin-bottom: 10px;">
                                <p><strong><?php echo esc_html__('Basis gebruik:', 'onderhoudskwaliteit-verbetersessie'); ?></strong></p>
                                <code>[ovm_afgerond_tabel]</code>
                            </div>
                            
                            <div style="background: #f0f0f1; padding: 15px; border-radius: 5px; margin-bottom: 10px;">
                                <p><strong><?php echo esc_html__('Met aangepaste titel:', 'onderhoudskwaliteit-verbetersessie'); ?></strong></p>
                                <code>[ovm_afgerond_tabel titel="Klantfeedback Verbeteringen"]</code>
                            </div>
                            
                            <div style="background: #f0f0f1; padding: 15px; border-radius: 5px; margin-bottom: 10px;">
                                <p><strong><?php echo esc_html__('Zonder zoekbalk, 20 items per pagina:', 'onderhoudskwaliteit-verbetersessie'); ?></strong></p>
                                <code>[ovm_afgerond_tabel toon_zoekbalk="nee" items_per_pagina="20"]</code>
                            </div>
                            
                            <div style="background: #f0f0f1; padding: 15px; border-radius: 5px;">
                                <p><strong><?php echo esc_html__('Volledig aangepast:', 'onderhoudskwaliteit-verbetersessie'); ?></strong></p>
                                <code>[ovm_afgerond_tabel titel="Verbeterprojecten 2024" toon_zoekbalk="ja" items_per_pagina="15" taal="nl"]</code>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <?php echo esc_html__('Weergegeven Kolommen', 'onderhoudskwaliteit-verbetersessie'); ?>
                        </th>
                        <td>
                            <p class="description">
                                <?php echo esc_html__('De tabel toont de volgende kolommen:', 'onderhoudskwaliteit-verbetersessie'); ?>
                            </p>
                            <ol style="list-style: decimal; margin-left: 20px;">
                                <li><?php echo esc_html__('Datum Inzending - Wanneer de opmerking oorspronkelijk is geplaatst', 'onderhoudskwaliteit-verbetersessie'); ?></li>
                                <li><?php echo esc_html__('Opmerking - De originele feedback van de klant', 'onderhoudskwaliteit-verbetersessie'); ?></li>
                                <li><?php echo esc_html__('Reactie - De admin reactie op de feedback', 'onderhoudskwaliteit-verbetersessie'); ?></li>
                                <li><?php echo esc_html__('Afgerond op - Datum waarop de status "Afgerond" is gezet', 'onderhoudskwaliteit-verbetersessie'); ?></li>
                            </ol>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" 
                           name="save_settings" 
                           class="button-primary" 
                           value="<?php echo esc_attr__('Instellingen opslaan', 'onderhoudskwaliteit-verbetersessie'); ?>" />
                    <span class="ovm-settings-save-indicator"></span>
                </p>
            </form>
        </div>
        <?php
    }
}