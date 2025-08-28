<?php
/**
 * Simple PDF Generator using FPDF
 * 
 * Download FPDF and include it here, or use a WordPress-compatible PDF solution
 */

// For now, let's create a simple CSV export as an alternative
class OGM_Simple_PDF {
    
    /**
     * Generate CSV export instead of PDF (more reliable)
     */
    public static function generate_csv($data, $filename) {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($data));
        
        echo "\xEF\xBB\xBF"; // UTF-8 BOM
        echo $data;
        exit;
    }
    
    /**
     * Generate CSV with original report layout (Users as rows, Weeks as columns)
     */
    public static function format_data_for_csv($login_data, $comments_data) {
        $csv_data = array();
        $current_week = (int) date('W');
        $last_complete_week = $current_week - 1;
        
        if ($last_complete_week < 1) {
            $last_complete_week = 1;
        }
        
        // Create headers: User, W1, W2, W3, ..., W[last_complete_week], Total, Mobile, Desktop, Active Weeks
        $headers = array('Gebruiker');
        for ($week = 1; $week <= $last_complete_week; $week++) {
            $headers[] = 'W' . $week;
        }
        $headers[] = 'Totaal';
        $headers[] = 'Mobiel';
        $headers[] = 'Desktop';
        $headers[] = 'Actieve Weken';
        $headers[] = 'Laatste Login';
        
        // LOGIN DATA TABLE (clean structure - no duplicate headers or dividers)
        $csv_data[] = $headers;
        
        // Process login data
        foreach ($login_data as $user_id => $user_data) {
            $row = array($user_data['display_name']);
            
            $total_logins = 0;
            $active_weeks = 0;
            $mobile_total = 0;
            $desktop_total = 0;
            
            // Add weekly data (EXCLUDE current week W27)
            for ($week = 1; $week <= $last_complete_week; $week++) {
                $logins = isset($user_data['weekly_data'][$week]) ? $user_data['weekly_data'][$week] : 0;
                $row[] = $logins > 0 ? $logins : '-';
                $total_logins += $logins;
                if ($logins > 0) $active_weeks++;
            }
            
            // Calculate proper device breakdown from weekly data (not totals from all time)
            // We need to sum mobile/desktop from only the weeks we're showing
            for ($week = 1; $week <= $last_complete_week; $week++) {
                if (isset($user_data['weekly_data'][$week]) && $user_data['weekly_data'][$week] > 0) {
                    // For now, use proportional split based on user's overall ratio
                    $week_logins = $user_data['weekly_data'][$week];
                    $user_total = ($user_data['mobile_logins'] ?? 0) + ($user_data['desktop_logins'] ?? 0);
                    
                    if ($user_total > 0) {
                        $mobile_ratio = ($user_data['mobile_logins'] ?? 0) / $user_total;
                        $mobile_total += round($week_logins * $mobile_ratio);
                        $desktop_total += round($week_logins * (1 - $mobile_ratio));
                    } else {
                        // If no device data, assume all desktop
                        $desktop_total += $week_logins;
                    }
                }
            }
            
            // Add summary columns
            $row[] = $total_logins;
            $row[] = $mobile_total;
            $row[] = $desktop_total;
            $row[] = $active_weeks;
            $row[] = isset($user_data['last_login']) ? date('d-m-Y H:i:s', strtotime($user_data['last_login'])) : '-';
            
            $csv_data[] = $row;
        }
        
        // COMMENTS DATA TABLE (separate CSV - cleaner approach)
        if (!empty($comments_data)) {
            $csv_data[] = array(); // Single empty row separator
            
            // Headers for comments (no mobile/desktop columns)
            $comment_headers = array('Gebruiker');
            for ($week = 1; $week <= $last_complete_week; $week++) {
                $comment_headers[] = 'W' . $week;
            }
            $comment_headers[] = 'Totaal';
            $comment_headers[] = 'Actieve Weken';
            $comment_headers[] = 'Laatste Reactie';
            
            $csv_data[] = $comment_headers;
            
            // Process comments data
            foreach ($comments_data as $user_id => $user_data) {
                $row = array($user_data['display_name']);
                
                $total_comments = 0;
                $active_weeks = 0;
                
                // Add weekly data (EXCLUDE current week)
                for ($week = 1; $week <= $last_complete_week; $week++) {
                    $comments = isset($user_data['weekly_data'][$week]) ? $user_data['weekly_data'][$week] : 0;
                    $row[] = $comments > 0 ? $comments : '-';
                    $total_comments += $comments;
                    if ($comments > 0) $active_weeks++;
                }
                
                // Add summary columns
                $row[] = $total_comments;
                $row[] = $active_weeks;
                $row[] = isset($user_data['last_comment']) ? date('d-m-Y H:i:s', strtotime($user_data['last_comment'])) : '-';
                
                $csv_data[] = $row;
            }
        }
        
        // Convert to CSV string
        $output = '';
        foreach ($csv_data as $row) {
            $output .= implode(',', array_map(function($field) {
                return '"' . str_replace('"', '""', $field) . '"';
            }, $row)) . "\n";
        }
        
        return $output;
    }
}