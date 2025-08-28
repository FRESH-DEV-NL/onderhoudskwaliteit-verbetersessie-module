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
        
        wp_enqueue_style(
            'ovm-admin',
            OVM_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            OVM_VERSION
        );
        
        wp_enqueue_script(
            'ovm-admin',
            OVM_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
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
                <?php echo esc_html__('Export naar CSV', 'onderhoudskwaliteit-verbetersessie'); ?>
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
                        <th scope="col" class="manage-column"><?php echo esc_html__('Artikel', 'onderhoudskwaliteit-verbetersessie'); ?></th>
                        <th scope="col" class="manage-column"><?php echo esc_html__('Datum', 'onderhoudskwaliteit-verbetersessie'); ?></th>
                        <th scope="col" class="manage-column"><?php echo esc_html__('Door', 'onderhoudskwaliteit-verbetersessie'); ?></th>
                        <th scope="col" class="manage-column"><?php echo esc_html__('Opmerking', 'onderhoudskwaliteit-verbetersessie'); ?></th>
                        <th scope="col" class="manage-column"><?php echo esc_html__('Reactie', 'onderhoudskwaliteit-verbetersessie'); ?></th>
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
                        <th scope="col" class="manage-column"><?php echo esc_html__('Artikel', 'onderhoudskwaliteit-verbetersessie'); ?></th>
                        <th scope="col" class="manage-column"><?php echo esc_html__('Datum', 'onderhoudskwaliteit-verbetersessie'); ?></th>
                        <th scope="col" class="manage-column"><?php echo esc_html__('Door', 'onderhoudskwaliteit-verbetersessie'); ?></th>
                        <th scope="col" class="manage-column"><?php echo esc_html__('Opmerking', 'onderhoudskwaliteit-verbetersessie'); ?></th>
                        <th scope="col" class="manage-column"><?php echo esc_html__('Reactie', 'onderhoudskwaliteit-verbetersessie'); ?></th>
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
        $comments = $this->data_manager->get_comments_by_status($status, $page_id);
        
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
     * Render single comment row
     */
    private function render_single_comment_row($comment, $status) {
        $post_link = get_permalink($comment->post_id);
        $metadata = maybe_unserialize($comment->metadata);
        
        $truncated_content = $this->truncate_with_formatting($comment->comment_content, 200);
        $full_content_class = strlen($comment->comment_content) > 200 ? 'has-more' : '';
        
        ?>
        <tr data-comment-id="<?php echo esc_attr($comment->id); ?>">
            <th scope="row" class="check-column">
                <input type="checkbox" name="comment_ids[]" value="<?php echo esc_attr($comment->id); ?>">
            </th>
            <td>
                <a href="<?php echo esc_url($post_link); ?>" target="_blank">
                    <?php echo esc_html($comment->post_title); ?>
                </a>
            </td>
            <td>
                <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($comment->comment_date))); ?>
            </td>
            <td>
                <span class="ovm-author-name" title="<?php echo esc_attr($comment->author_email); ?>">
                    <?php echo esc_html($comment->author_name); ?>
                </span>
                <?php if ($comment->rating): ?>
                    <span class="ovm-rating">
                        <?php echo str_repeat('★', $comment->rating) . str_repeat('☆', 5 - $comment->rating); ?>
                    </span>
                <?php endif; ?>
            </td>
            <td class="ovm-comment-content <?php echo esc_attr($full_content_class); ?>">
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
            <td>
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
            <td>
                <?php $this->render_action_buttons($comment->id, $status); ?>
            </td>
        </tr>
        <?php
    }
    
    /**
     * Render action buttons
     */
    private function render_action_buttons($comment_id, $status) {
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
        $chatgpt_api_key = get_option('ovm_chatgpt_api_key', '');
        $chatgpt_prompt = get_option('ovm_chatgpt_prompt', 'Herschrijf deze tekst maar behoud de toon en zorg dat er geen spelfouten in de tekst zit: [reactie_tekst]');
        ?>
        <div class="ovm-settings-container">
            <h3><?php echo esc_html__('ChatGPT Instellingen', 'onderhoudskwaliteit-verbetersessie'); ?></h3>
            
            <form id="ovm-settings-form" method="post">
                <?php wp_nonce_field('ovm_save_settings', 'ovm_settings_nonce'); ?>
                
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
        
        // Handle form submission
        if (isset($_POST['save_settings']) && wp_verify_nonce($_POST['ovm_settings_nonce'], 'ovm_save_settings')) {
            update_option('ovm_chatgpt_api_key', sanitize_text_field($_POST['chatgpt_api_key']));
            update_option('ovm_chatgpt_prompt', sanitize_textarea_field($_POST['chatgpt_prompt']));
            
            echo '<div class="notice notice-success is-dismissible"><p>' . 
                 esc_html__('Instellingen opgeslagen!', 'onderhoudskwaliteit-verbetersessie') . 
                 '</p></div>';
        }
    }
}