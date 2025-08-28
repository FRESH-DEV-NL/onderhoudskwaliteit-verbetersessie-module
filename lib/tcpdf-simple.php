<?php
/**
 * Simple TCPDF-based PDF Generator
 * 
 * Fallback PDF generator using TCPDF that doesn't require external dependencies
 */

class OGM_TCPDF_Generator {
    
    /**
     * Generate PDF from HTML content
     *
     * @param string $html HTML content
     * @param string $filename Output filename
     * @return void
     */
    public static function generate_pdf($html, $filename) {
        // Check if TCPDF is available
        if (!class_exists('TCPDF')) {
            // Try to load TCPDF from WordPress
            $tcpdf_paths = array(
                ABSPATH . 'wp-includes/class-phpass.php', // WordPress doesn't include TCPDF
                '/usr/share/php/tcpdf/tcpdf.php',
                '/var/www/html/tcpdf/tcpdf.php'
            );
            
            $tcpdf_loaded = false;
            foreach ($tcpdf_paths as $path) {
                if (file_exists($path)) {
                    require_once($path);
                    $tcpdf_loaded = true;
                    break;
                }
            }
            
            if (!$tcpdf_loaded) {
                // Fall back to HTML output if no PDF library available
                self::output_html_as_pdf($html, $filename);
                return;
            }
        }
        
        // Double check TCPDF is really available before using it
        if (!class_exists('TCPDF')) {
            self::output_html_as_pdf($html, $filename);
            return;
        }
        
        try {
            // Create new PDF document
            $pdf = new TCPDF('L', PDF_UNIT, 'A4', true, 'UTF-8', false);
            
            // Set document information
            $pdf->SetCreator('Onderhoudskwaliteit Gebruik Module');
            $pdf->SetAuthor('Fresh-Dev');
            $pdf->SetTitle('Gebruikersrapport - ' . date('d-m-Y'));
            
            // Set default header/footer data
            $pdf->SetHeaderData('', 0, 'Gebruikersrapport', date('d-m-Y'));
            $pdf->setFooterData(array(0,64,0), array(0,64,128));
            
            // Set header and footer fonts
            $pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
            $pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));
            
            // Set default monospaced font
            $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
            
            // Set margins
            $pdf->SetMargins(10, 20, 10);
            $pdf->SetHeaderMargin(5);
            $pdf->SetFooterMargin(10);
            
            // Set auto page breaks
            $pdf->SetAutoPageBreak(TRUE, 25);
            
            // Add a page
            $pdf->AddPage();
            
            // Set font
            $pdf->SetFont('helvetica', '', 8);
            
            // Write HTML content
            $pdf->writeHTML($html, true, false, true, false, '');
            
            // Output PDF
            $pdf->Output($filename, 'D');
            
        } catch (Exception $e) {
            // Fall back to HTML output
            self::output_html_as_pdf($html, $filename);
        }
    }
    
    /**
     * Generate PDF content as string for email attachment
     *
     * @param string $html HTML content
     * @param string $filename Output filename (for reference)
     * @return string|false PDF content as string or false on failure
     */
    public static function generate_pdf_content($html, $filename) {
        // Check if TCPDF is available
        if (!class_exists('TCPDF')) {
            // Return prepared HTML content if TCPDF not available
            return self::prepare_html_for_download($html);
        }
        
        try {
            // Create new PDF document
            $pdf = new TCPDF('L', PDF_UNIT, 'A4', true, 'UTF-8', false);
            
            // Set document information
            $pdf->SetCreator('Onderhoudskwaliteit Gebruik Module');
            $pdf->SetAuthor('Fresh-Dev');
            $pdf->SetTitle('Gebruikersrapport - ' . date('d-m-Y'));
            
            // Set default header/footer data
            $pdf->SetHeaderData('', 0, 'Gebruikersrapport', date('d-m-Y'));
            $pdf->setFooterData(array(0,64,0), array(0,64,128));
            
            // Set header and footer fonts
            $pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
            $pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));
            
            // Set default monospaced font
            $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
            
            // Set margins
            $pdf->SetMargins(10, 20, 10);
            $pdf->SetHeaderMargin(5);
            $pdf->SetFooterMargin(10);
            
            // Set auto page breaks
            $pdf->SetAutoPageBreak(TRUE, 25);
            
            // Add a page
            $pdf->AddPage();
            
            // Set font
            $pdf->SetFont('helvetica', '', 8);
            
            // Write HTML content
            $pdf->writeHTML($html, true, false, true, false, '');
            
            // Return PDF content as string
            return $pdf->Output('', 'S');
            
        } catch (Exception $e) {
            // Return prepared HTML content as fallback
            return self::prepare_html_for_download($html);
        }
    }
    
    /**
     * Output HTML as downloadable file (fallback)
     *
     * @param string $html HTML content
     * @param string $filename Output filename
     * @return void
     */
    private static function output_html_as_pdf($html, $filename) {
        // Clean the HTML for better display
        $clean_html = self::prepare_html_for_download($html);
        
        // Set headers for download
        header('Content-Type: text/html; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . str_replace('.pdf', '.html', $filename) . '"');
        header('Content-Length: ' . strlen($clean_html));
        
        echo $clean_html;
        exit;
    }
    
    /**
     * Prepare HTML for standalone viewing
     *
     * @param string $html Original HTML
     * @return string Prepared HTML
     */
    private static function prepare_html_for_download($html) {
        $full_html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Gebruikersrapport - ' . date('d-m-Y') . '</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        table { border-collapse: collapse; width: 100%; margin: 10px 0; }
        th, td { border: 1px solid #ddd; padding: 4px; text-align: center; }
        th { background-color: #f2f2f2; font-weight: bold; }
        .summary-table { margin: 20px 0; }
        .summary-table th { background-color: #e7f3ff; }
        .page-break { page-break-before: always; }
        @media print {
            body { margin: 0; }
            table { font-size: 10px; }
        }
    </style>
</head>
<body>
' . $html . '
</body>
</html>';
        
        return $full_html;
    }
}