<?php
/**
 * Shortcode Class
 * 
 * Handles the frontend display shortcode
 */

if (!defined('ABSPATH')) {
    exit;
}

class OVM_Shortcode {
    
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
        add_shortcode('ovm_afgerond_tabel', array($this, 'render_completed_table'));
    }
    
    /**
     * Render the completed reviews table
     */
    public function render_completed_table($atts) {
        // Parse shortcode attributes
        $atts = shortcode_atts(array(
            'titel' => __('Verbetersessie', 'onderhoudskwaliteit-verbetersessie'),
            'toon_zoekbalk' => 'ja',
            'items_per_pagina' => 10,
            'taal' => 'nl'
        ), $atts, 'ovm_afgerond_tabel');
        
        // Enqueue required scripts and styles
        $this->enqueue_frontend_assets();
        
        // Get completed comments
        $comments = $this->data_manager->get_comments_by_status('afgerond');
        
        // Start output buffering
        ob_start();
        ?>
        <div class="ovm-frontend-wrapper">
            <?php if (!empty($atts['titel'])): ?>
                <h2 class="ovm-frontend-title"><?php echo esc_html($atts['titel']); ?></h2>
            <?php endif; ?>
            
            <?php if (empty($comments)): ?>
                <p class="ovm-no-results"><?php echo esc_html__('Er zijn momenteel geen afgeronde verbeteringen.', 'onderhoudskwaliteit-verbetersessie'); ?></p>
            <?php else: ?>
                <!-- Filters -->
                <div class="ovm-frontend-filters">
                    <!-- Article filter -->
                    <div class="ovm-article-filter">
                        <label for="ovm-article-select">
                            <?php echo esc_html__('Filter op artikel:', 'onderhoudskwaliteit-verbetersessie'); ?>
                        </label>
                        <select id="ovm-article-select" class="ovm-article-select">
                            <option value=""><?php echo esc_html__('Alle pagina\'s', 'onderhoudskwaliteit-verbetersessie'); ?></option>
                            <?php 
                            // Get unique posts from completed comments
                            $posts = $this->data_manager->get_posts_with_comments('afgerond');
                            foreach ($posts as $post): ?>
                                <option value="<?php echo esc_attr($post->post_id); ?>">
                                    <?php echo esc_html($post->post_title); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Date range filter -->
                    <div class="ovm-date-filter">
                        <label>
                            <?php echo esc_html__('Periode afgerond datum:', 'onderhoudskwaliteit-verbetersessie'); ?>
                        </label>
                        <input type="date" id="min-date" placeholder="Van datum">
                        <span>-</span>
                        <input type="date" id="max-date" placeholder="Tot datum">
                        <button type="button" id="reset-dates" class="button button-small">
                            <?php echo esc_html__('Reset', 'onderhoudskwaliteit-verbetersessie'); ?>
                        </button>
                    </div>
                </div>
                <table id="ovm-datatable" class="display responsive nowrap" style="width:100%">
                    <thead>
                        <tr>
                            <th><?php echo esc_html__('Datum Inzending', 'onderhoudskwaliteit-verbetersessie'); ?></th>
                            <th><?php echo esc_html__('Artikel', 'onderhoudskwaliteit-verbetersessie'); ?></th>
                            <th><?php echo esc_html__('Opmerking', 'onderhoudskwaliteit-verbetersessie'); ?></th>
                            <th><?php echo esc_html__('Reactie', 'onderhoudskwaliteit-verbetersessie'); ?></th>
                            <th><?php echo esc_html__('Afgerond op', 'onderhoudskwaliteit-verbetersessie'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($comments as $comment): ?>
                            <tr data-post-id="<?php echo esc_attr($comment->post_id); ?>">
                                <td data-sort="<?php echo esc_attr(strtotime($comment->comment_date)); ?>">
                                    <?php echo esc_html(date_i18n('d-m-Y', strtotime($comment->comment_date))); ?>
                                </td>
                                <td>
                                    <?php 
                                    $post_link = get_permalink($comment->post_id);
                                    if ($post_link): ?>
                                        <a href="<?php echo esc_url($post_link); ?>" target="_blank">
                                            <?php echo esc_html($comment->post_title); ?>
                                        </a>
                                    <?php else: ?>
                                        <?php echo esc_html($comment->post_title); ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="ovm-comment-content">
                                        <?php echo nl2br(esc_html($this->fix_text_encoding($comment->comment_content))); ?>
                                    </div>
                                    <?php if (!empty($comment->author_name)): ?>
                                        <small class="ovm-author-info">
                                            <?php echo esc_html__('Door:', 'onderhoudskwaliteit-verbetersessie'); ?> 
                                            <strong><?php echo esc_html($comment->author_name); ?></strong>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($comment->admin_response)): ?>
                                        <div class="ovm-admin-response">
                                            <?php echo nl2br(esc_html($this->fix_text_encoding($comment->admin_response))); ?>
                                        </div>
                                    <?php else: ?>
                                        <em><?php echo esc_html__('Geen reactie', 'onderhoudskwaliteit-verbetersessie'); ?></em>
                                    <?php endif; ?>
                                </td>
                                <td data-sort="<?php echo esc_attr(strtotime($comment->status_changed_date)); ?>">
                                    <?php 
                                    if (!empty($comment->status_changed_date)) {
                                        echo esc_html(date_i18n('d-m-Y', strtotime($comment->status_changed_date)));
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <script>
                jQuery(document).ready(function($) {
                    // DataTables language settings
                    var language = <?php echo ($atts['taal'] === 'nl') ? 'ovm_dutch_lang' : '{}'; ?>;
                    
                    // Initialize DataTable
                    var table = $('#ovm-datatable').DataTable({
                        responsive: true,
                        autoWidth: false,
                        pageLength: <?php echo intval($atts['items_per_pagina']); ?>,
                        searching: <?php echo ($atts['toon_zoekbalk'] === 'ja') ? 'true' : 'false'; ?>,
                        order: [[4, 'desc']], // Sort by completion date descending (now column 4 instead of 3)
                        language: language,
                        dom: 'lfrtip', // Ensure search box is shown
                        columnDefs: [
                            {
                                targets: 0, // Datum column
                                width: '10%'
                            },
                            {
                                targets: 1, // Artikel column
                                width: '20%',
                                className: 'text-wrap'
                            },
                            {
                                targets: 2, // Opmerking column
                                width: '30%',
                                className: 'text-wrap'
                            },
                            {
                                targets: 3, // Reactie column  
                                width: '30%',
                                className: 'text-wrap'
                            },
                            {
                                targets: 4, // Afgerond op column
                                width: '10%'
                            }
                        ]
                    });
                    
                    // Custom filtering function for both article and date range
                    $.fn.dataTable.ext.search.push(
                        function(settings, data, dataIndex) {
                            // Article filter
                            var selectedArticle = $('#ovm-article-select').val();
                            if (selectedArticle) {
                                var row = table.row(dataIndex).node();
                                var postId = $(row).attr('data-post-id');
                                if (postId !== selectedArticle) {
                                    return false;
                                }
                            }
                            
                            // Date range filter
                            var minDate = $('#min-date').val();
                            var maxDate = $('#max-date').val();
                            
                            // Get the date from the 5th column (Afgerond op) - now index 4
                            var dateColumn = data[4]; // Index 4 = 5th column
                            
                            // If no date filters are set, pass date check
                            if (minDate || maxDate) {
                                // Extract the sort value from data attribute if available
                                var row = table.row(dataIndex).node();
                                var sortValue = $(row).find('td').eq(4).attr('data-sort');
                                
                                if (sortValue) {
                                    // Convert timestamp to date string YYYY-MM-DD
                                    var rowDate = new Date(parseInt(sortValue) * 1000);
                                    var rowDateStr = rowDate.getFullYear() + '-' + 
                                                   String(rowDate.getMonth() + 1).padStart(2, '0') + '-' + 
                                                   String(rowDate.getDate()).padStart(2, '0');
                                    
                                    // Check date range
                                    if (minDate && rowDateStr < minDate) {
                                        return false;
                                    }
                                    if (maxDate && rowDateStr > maxDate) {
                                        return false;
                                    }
                                }
                            }
                            
                            return true;
                        }
                    );
                    
                    // Event handler for article filter
                    $('#ovm-article-select').on('change', function() {
                        table.draw();
                    });
                    
                    // Event handlers for date inputs
                    $('#min-date, #max-date').on('change', function() {
                        table.draw();
                    });
                    
                    // Reset button for dates
                    $('#reset-dates').on('click', function() {
                        $('#min-date').val('');
                        $('#max-date').val('');
                        table.draw();
                    });
                });
                </script>
            <?php endif; ?>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Enqueue frontend assets
     */
    private function enqueue_frontend_assets() {
        // DataTables CSS
        wp_enqueue_style(
            'datatables',
            'https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css',
            array(),
            '1.13.7'
        );
        
        wp_enqueue_style(
            'datatables-responsive',
            'https://cdn.datatables.net/responsive/2.5.0/css/responsive.dataTables.min.css',
            array('datatables'),
            '2.5.0'
        );
        
        // Custom frontend CSS
        wp_enqueue_style(
            'ovm-frontend',
            OVM_PLUGIN_URL . 'assets/css/frontend.css',
            array('datatables'),
            OVM_VERSION
        );
        
        // jQuery (usually already loaded by WordPress)
        wp_enqueue_script('jquery');
        
        // DataTables JS
        wp_enqueue_script(
            'datatables',
            'https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js',
            array('jquery'),
            '1.13.7',
            true
        );
        
        wp_enqueue_script(
            'datatables-responsive',
            'https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js',
            array('datatables'),
            '2.5.0',
            true
        );
        
        // Add Dutch language for DataTables
        wp_localize_script('datatables', 'ovm_dutch_lang', array(
            'sProcessing' => 'Bezig...',
            'sLengthMenu' => '_MENU_ resultaten weergeven',
            'sZeroRecords' => 'Geen resultaten gevonden',
            'sInfo' => '_START_ tot _END_ van _TOTAL_ resultaten',
            'sInfoEmpty' => 'Geen resultaten om weer te geven',
            'sInfoFiltered' => '(gefilterd uit _MAX_ resultaten)',
            'sSearch' => 'Zoeken:',
            'oPaginate' => array(
                'sFirst' => 'Eerste',
                'sPrevious' => 'Vorige',
                'sNext' => 'Volgende',
                'sLast' => 'Laatste'
            )
        ));
    }
    
    /**
     * Fix text encoding issues
     */
    private function fix_text_encoding($text) {
        if (empty($text)) {
            return '';
        }
        
        // Ensure UTF-8 encoding
        if (!mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'auto');
        }
        
        // Replace problematic characters
        $replacements = array(
            "\u201C" => '"',
            "\u201D" => '"',
            "\u2018" => "'",
            "\u2019" => "'",
            "\u2013" => '-',
            "\u2014" => '-',
            "\u2026" => '...',
            "\u20AC" => 'EUR',
            "\u00AE" => '(R)',
            "\u00A9" => '(C)',
            "\u2122" => '(TM)',
        );
        
        $text = str_replace(array_keys($replacements), array_values($replacements), $text);
        
        // Remove control characters
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);
        
        return $text;
    }
}