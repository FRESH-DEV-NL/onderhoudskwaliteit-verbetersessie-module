<?php
/**
 * Professional PDF Generator using mPDF
 * 
 * Creates beautiful PDF reports with login and comment data
 */

require_once(OGM_PLUGIN_PATH . 'lib/mpdf-autoload.php');

if (!class_exists('OGM_PDF_Generator')) {
class OGM_PDF_Generator {
    
    /**
     * Generate professional PDF with login and comment data
     */
    public static function generate_pdf($login_data, $comments_data, $filename) {
        try {
            // Initialize mPDF
            $mpdf = new \Mpdf\Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4-L', // Landscape for wide table
                'orientation' => 'L',
                'margin_left' => 10,
                'margin_right' => 10,
                'margin_top' => 20,
                'margin_bottom' => 20,
                'default_font' => 'dejavusans'
            ]);
            
            // Set document properties
            $mpdf->SetTitle('Gebruikersrapport - ' . date('d-m-Y'));
            $mpdf->SetAuthor('Fresh-Dev');
            $mpdf->SetCreator('Onderhoudskwaliteit Gebruik Module');
            
            // Generate HTML content
            $html = self::generate_html_content($login_data, $comments_data);
            
            // Add HTML to PDF
            $mpdf->WriteHTML($html);
            
            // Output PDF
            $mpdf->Output($filename, \Mpdf\Output\Destination::DOWNLOAD);
            
        } catch (Exception $e) {
            wp_die('PDF generation error: ' . $e->getMessage());
        }
    }
    
    /**
     * Generate PDF as string
     */
    public static function generate_pdf_string($login_data, $comments_data) {
        try {
            // Initialize mPDF
            $mpdf = new \Mpdf\Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4-L', // Landscape for wide table
                'orientation' => 'L',
                'margin_left' => 10,
                'margin_right' => 10,
                'margin_top' => 20,
                'margin_bottom' => 20,
                'default_font' => 'dejavusans'
            ]);
            
            // Set document properties
            $mpdf->SetTitle('Gebruikersrapport - ' . date('d-m-Y'));
            $mpdf->SetAuthor('Fresh-Dev');
            $mpdf->SetCreator('Onderhoudskwaliteit Gebruik Module');
            
            // Generate HTML content
            $html = self::generate_html_content($login_data, $comments_data);
            
            // Add HTML to PDF
            $mpdf->WriteHTML($html);
            
            // Return PDF as string
            return $mpdf->Output('', \Mpdf\Output\Destination::STRING_RETURN);
            
        } catch (Exception $e) {
            throw new Exception('PDF generation error: ' . $e->getMessage());
        }
    }
    
    /**
     * Generate HTML content for PDF
     */
    private static function generate_html_content($login_data, $comments_data) {
        $current_week = (int) date('W');
        $last_complete_week = $current_week - 1;
        
        if ($last_complete_week < 1) {
            $last_complete_week = 1;
        }
        
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: DejaVu Sans, sans-serif; font-size: 9px; }
                .header { text-align: center; margin-bottom: 20px; }
                .header h1 { color: #007cba; margin: 0; font-size: 16px; }
                .header p { margin: 5px 0; color: #666; }
                
                .summary { margin: 20px 0; padding: 10px; background: #f9f9f9; border: 1px solid #ddd; }
                .summary h3 { margin: 0 0 10px 0; color: #007cba; }
                .summary-grid { display: table; width: 100%; }
                .summary-row { display: table-row; }
                .summary-cell { display: table-cell; padding: 5px; border-bottom: 1px solid #eee; }
                .summary-cell:first-child { font-weight: bold; width: 200px; }
                
                .data-table { width: 100%; border-collapse: collapse; margin: 20px 0; font-size: 8px; }
                .data-table th { background: #007cba; color: white; padding: 6px 3px; border: 1px solid #005a87; text-align: center; font-weight: bold; }
                .data-table td { padding: 4px 3px; border: 1px solid #ddd; text-align: center; }
                .data-table tr:nth-child(even) { background: #f9f9f9; }
                .data-table tr:hover { background: #f0f8ff; }
                
                .user-cell { text-align: left !important; font-weight: bold; background: #f5f5f5; max-width: 100px; }
                .total-cell { background: #e7f3ff; font-weight: bold; }
                .active-cell { background: #e8f5e8; }
                .inactive-cell { color: #999; }
                
                .section { margin: 30px 0; }
                .section h2 { color: #007cba; border-bottom: 2px solid #007cba; padding-bottom: 5px; }
                
                .footer { margin-top: 30px; text-align: center; font-size: 8px; color: #666; }
                .page-break { page-break-before: always; }
            </style>
        </head>
        <body>';
        
        // Header
        $html .= '
        <div class="header">
            <h1>📊 Gebruikersrapport</h1>
            <p>Periode: Week 1 t/m Week ' . $last_complete_week . ' van ' . date('Y') . '</p>
            <p>Gegenereerd op: ' . date('d-m-Y H:i:s') . ' (' . wp_timezone_string() . ')</p>
        </div>';
        
        // Summary
        $html .= self::generate_summary_section($login_data, $comments_data, $last_complete_week);
        
        // Login Data Table
        if (!empty($login_data['users'])) {
            $html .= '<div class="section">';
            $html .= '<h2>Login Gegevens</h2>';
            $html .= self::generate_login_table($login_data['users'], $last_complete_week);
            $html .= '</div>';
        }
        
        // Comments Data Table
        if (!empty($comments_data['users'])) {
            $html .= '<div class="section page-break">';
            $html .= '<h2>Reactie Gegevens</h2>';
            $html .= self::generate_comments_table($comments_data['users'], $last_complete_week);
            $html .= '</div>';
        }
        
        // Footer
        $html .= '
        <div class="footer">
            <p>Automatisch gegenereerd door Onderhoudskwaliteit Gebruik Module | Fresh-Dev | Versie ' . OGM_PLUGIN_VERSION . '</p>
        </div>
        
        </body>
        </html>';
        
        return $html;
    }
    
    /**
     * Generate summary section
     */
    private static function generate_summary_section($login_data, $comments_data, $last_complete_week) {
        $total_logins = 0;
        $total_comments = 0;
        $active_login_users = 0;
        $active_comment_users = 0;
        
        // Calculate totals
        if (!empty($login_data['users'])) {
            foreach ($login_data['users'] as $user_data) {
                $user_total = 0;
                for ($week = 1; $week <= $last_complete_week; $week++) {
                    $user_total += isset($user_data['weekly_data'][$week]) ? $user_data['weekly_data'][$week] : 0;
                }
                $total_logins += $user_total;
                if ($user_total > 0) $active_login_users++;
            }
        }
        
        if (!empty($comments_data['users'])) {
            foreach ($comments_data['users'] as $user_data) {
                $user_total = 0;
                for ($week = 1; $week <= $last_complete_week; $week++) {
                    $user_total += isset($user_data['weekly_data'][$week]) ? $user_data['weekly_data'][$week] : 0;
                }
                $total_comments += $user_total;
                if ($user_total > 0) $active_comment_users++;
            }
        }
        
        return '
        <div class="summary">
            <h3>Samenvatting</h3>
            <div class="summary-grid">
                <div class="summary-row">
                    <div class="summary-cell">Totaal aantal logins:</div>
                    <div class="summary-cell">' . number_format($total_logins) . '</div>
                </div>
                <div class="summary-row">
                    <div class="summary-cell">Totaal aantal reacties:</div>
                    <div class="summary-cell">' . number_format($total_comments) . '</div>
                </div>
                <div class="summary-row">
                    <div class="summary-cell">Actieve login gebruikers:</div>
                    <div class="summary-cell">' . $active_login_users . '</div>
                </div>
                <div class="summary-row">
                    <div class="summary-cell">Actieve reactie gebruikers:</div>
                    <div class="summary-cell">' . $active_comment_users . '</div>
                </div>
                <div class="summary-row">
                    <div class="summary-cell">Rapportage periode:</div>
                    <div class="summary-cell">Week 1 - ' . $last_complete_week . ' (' . date('Y') . ')</div>
                </div>
            </div>
        </div>';
    }
    
    /**
     * Generate login data table
     */
    private static function generate_login_table($users, $last_complete_week) {
        $html = '<table class="data-table">';
        
        // Header
        $html .= '<thead><tr>';
        $html .= '<th class="user-cell">Gebruiker</th>';
        for ($week = 1; $week <= $last_complete_week; $week++) {
            $html .= '<th>W' . $week . '</th>';
        }
        $html .= '<th>Totaal</th>';
        $html .= '<th>Mobiel</th>';
        $html .= '<th>Desktop</th>';
        $html .= '<th>Actieve<br>Weken</th>';
        $html .= '<th>Laatste Login</th>';
        $html .= '</tr></thead>';
        
        // Body
        $html .= '<tbody>';
        foreach ($users as $user_data) {
            $html .= '<tr>';
            $html .= '<td class="user-cell">' . esc_html($user_data['display_name']) . '</td>';
            
            $total_logins = 0;
            $active_weeks = 0;
            $mobile_total = 0;
            $desktop_total = 0;
            
            // Weekly data
            for ($week = 1; $week <= $last_complete_week; $week++) {
                $logins = isset($user_data['weekly_data'][$week]) ? $user_data['weekly_data'][$week] : 0;
                $class = $logins > 0 ? 'active-cell' : 'inactive-cell';
                $display = $logins > 0 ? $logins : '-';
                $html .= '<td class="' . $class . '">' . $display . '</td>';
                
                $total_logins += $logins;
                if ($logins > 0) {
                    $active_weeks++;
                    // Calculate mobile/desktop split
                    $user_total = ($user_data['mobile_logins'] ?? 0) + ($user_data['desktop_logins'] ?? 0);
                    if ($user_total > 0) {
                        $mobile_ratio = ($user_data['mobile_logins'] ?? 0) / $user_total;
                        $mobile_total += round($logins * $mobile_ratio);
                        $desktop_total += round($logins * (1 - $mobile_ratio));
                    } else {
                        $desktop_total += $logins;
                    }
                }
            }
            
            // Summary columns
            $html .= '<td class="total-cell">' . $total_logins . '</td>';
            $html .= '<td>' . $mobile_total . '</td>';
            $html .= '<td>' . $desktop_total . '</td>';
            $html .= '<td>' . $active_weeks . '</td>';
            $html .= '<td>' . (isset($user_data['last_login']) ? date('d-m-Y', strtotime($user_data['last_login'])) : '-') . '</td>';
            
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';
        
        return $html;
    }
    
    /**
     * Generate comments data table
     */
    private static function generate_comments_table($users, $last_complete_week) {
        $html = '<table class="data-table">';
        
        // Header
        $html .= '<thead><tr>';
        $html .= '<th class="user-cell">Gebruiker</th>';
        for ($week = 1; $week <= $last_complete_week; $week++) {
            $html .= '<th>W' . $week . '</th>';
        }
        $html .= '<th>Totaal</th>';
        $html .= '<th>Actieve<br>Weken</th>';
        $html .= '<th>Laatste Reactie</th>';
        $html .= '</tr></thead>';
        
        // Body
        $html .= '<tbody>';
        foreach ($users as $user_data) {
            $html .= '<tr>';
            $html .= '<td class="user-cell">' . esc_html($user_data['display_name']) . '</td>';
            
            $total_comments = 0;
            $active_weeks = 0;
            
            // Weekly data
            for ($week = 1; $week <= $last_complete_week; $week++) {
                $comments = isset($user_data['weekly_data'][$week]) ? $user_data['weekly_data'][$week] : 0;
                $class = $comments > 0 ? 'active-cell' : 'inactive-cell';
                $display = $comments > 0 ? $comments : '-';
                $html .= '<td class="' . $class . '">' . $display . '</td>';
                
                $total_comments += $comments;
                if ($comments > 0) $active_weeks++;
            }
            
            // Summary columns
            $html .= '<td class="total-cell">' . $total_comments . '</td>';
            $html .= '<td>' . $active_weeks . '</td>';
            $html .= '<td>' . (isset($user_data['last_comment']) ? date('d-m-Y', strtotime($user_data['last_comment'])) : '-') . '</td>';
            
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';
        
        return $html;
    }
}
}