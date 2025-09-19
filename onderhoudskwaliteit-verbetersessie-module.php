<?php
/**
 * Plugin Name: Onderhoudskwaliteit Verbetersessie Module
 * Plugin URI: https://fresh-dev.nl
 * Description: Beheert WordPress comments/reviews in een workflow systeem met drie statussen voor verbetersessies.
 * Version: 2.0
 * Author: Fresh-Dev
 * Text Domain: onderhoudskwaliteit-verbetersessie
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) {
    exit;
}

define('OVM_VERSION', '2.0');
define('OVM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('OVM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('OVM_PLUGIN_FILE', __FILE__);

/**
 * Main plugin class
 */
class Onderhoudskwaliteit_Verbetersessie_Module {
    
    /**
     * Instance of this class
     */
    private static $instance = null;
    
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
        $this->init();
    }
    
    /**
     * Initialize the plugin
     */
    private function init() {
        $this->load_dependencies();
        $this->define_admin_hooks();
        
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    /**
     * Load required dependencies
     */
    private function load_dependencies() {
        require_once OVM_PLUGIN_DIR . 'includes/class-ovm-data-manager.php';
        require_once OVM_PLUGIN_DIR . 'includes/class-ovm-comment-tracker.php';
        require_once OVM_PLUGIN_DIR . 'includes/class-ovm-admin-page.php';
        require_once OVM_PLUGIN_DIR . 'includes/class-ovm-ajax-handler.php';
        require_once OVM_PLUGIN_DIR . 'includes/class-ovm-shortcode.php';
    }
    
    /**
     * Define admin hooks
     */
    private function define_admin_hooks() {
        $comment_tracker = OVM_Comment_Tracker::get_instance();
        $admin_page = OVM_Admin_Page::get_instance();
        $ajax_handler = OVM_Ajax_Handler::get_instance();
        $shortcode = OVM_Shortcode::get_instance();
        
        add_action('comment_post', array($comment_tracker, 'track_new_comment'), 10, 3);
        add_action('wp_insert_comment', array($comment_tracker, 'track_inserted_comment'), 10, 2);
        
        add_action('admin_menu', array($admin_page, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($admin_page, 'enqueue_admin_assets'));
        add_action('admin_notices', array($this, 'show_admin_notices'));
        
        add_action('wp_ajax_ovm_save_response', array($ajax_handler, 'save_admin_response'));
        add_action('wp_ajax_ovm_change_status', array($ajax_handler, 'change_status'));
        add_action('wp_ajax_ovm_bulk_action', array($ajax_handler, 'handle_bulk_action'));
        add_action('wp_ajax_ovm_filter_by_page', array($ajax_handler, 'filter_by_page'));
        add_action('wp_ajax_ovm_get_posts_for_status', array($ajax_handler, 'get_posts_for_status'));
        add_action('wp_ajax_ovm_chatgpt_generate_response', array($ajax_handler, 'chatgpt_generate_response'));
        add_action('wp_ajax_ovm_delete_comment', array($ajax_handler, 'delete_comment'));
        add_action('wp_ajax_ovm_save_comment_content', array($ajax_handler, 'save_comment_content'));
        add_action('wp_ajax_ovm_import_comments', array($ajax_handler, 'import_comments'));
        add_action('wp_ajax_ovm_export_comments', array($ajax_handler, 'export_comments'));
        add_action('wp_ajax_ovm_update_missing_images', array($ajax_handler, 'update_missing_images'));
        add_action('wp_ajax_ovm_toggle_flag', array($ajax_handler, 'toggle_flag'));
        
        // Check for database updates
        add_action('admin_init', array($this, 'check_database_version'));
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        $data_manager = OVM_Data_Manager::get_instance();
        
        // Create database table
        if ($data_manager->create_database_table()) {
            // Import existing comments only if not already imported
            if (!get_option('ovm_comments_imported', false)) {
                $imported_count = $data_manager->import_existing_comments();
                
                // Schedule admin notice
                set_transient('ovm_activation_notice', array(
                    'type' => 'success',
                    'message' => sprintf(
                        __('%d bestaande comments zijn geïmporteerd in de Verbetersessie Module.', 'onderhoudskwaliteit-verbetersessie'),
                        $imported_count
                    )
                ), 30);
            }
        } else {
            // Schedule error notice
            set_transient('ovm_activation_notice', array(
                'type' => 'error',
                'message' => __('Er is een fout opgetreden bij het aanmaken van de database tabel.', 'onderhoudskwaliteit-verbetersessie')
            ), 30);
        }
        
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        flush_rewrite_rules();
    }
    
    /**
     * Check database version and update if needed
     */
    public function check_database_version() {
        $installed_version = get_option('ovm_db_version', '0');
        
        if (version_compare($installed_version, OVM_VERSION, '<')) {
            $data_manager = OVM_Data_Manager::get_instance();
            $data_manager->create_database_table();
        }
    }
    
    /**
     * Show admin notices
     */
    public function show_admin_notices() {
        // Show activation notice
        $notice = get_transient('ovm_activation_notice');
        if ($notice) {
            $class = $notice['type'] === 'error' ? 'notice-error' : 'notice-success';
            ?>
            <div class="notice <?php echo esc_attr($class); ?> is-dismissible">
                <p><?php echo esc_html($notice['message']); ?></p>
            </div>
            <?php
            delete_transient('ovm_activation_notice');
        }
    }
}

function ovm_init() {
    return Onderhoudskwaliteit_Verbetersessie_Module::get_instance();
}

add_action('plugins_loaded', 'ovm_init');