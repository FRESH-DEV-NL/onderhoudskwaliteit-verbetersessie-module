<?php
/**
 * Stub for FPDI trait to prevent fatal errors
 * We don't need PDF import functionality for this plugin
 */

namespace setasign\Fpdi;

trait FpdiTrait {
    // Properties that mPDF might expect
    protected $currentReaderId = null;
    protected $readers = array();
    protected $importedPages = array();
    
    // Stub implementation - we don't need PDF import functionality
    
    protected function writePdfType($value) {
        // Stub
    }
    
    protected function useImportedPage($pageId, $x = 0, $y = 0, $width = null, $height = null, $adjustPageSize = false) {
        // Stub
    }
    
    protected function importPage($pageNumber, $box = 'CropBox', $groupXObject = true) {
        // Stub
        return 0;
    }
    
    public function setSourceFile($file) {
        throw new \Exception('PDF import functionality is not available in this installation');
    }
    
    public function getTemplateSize($tpl, $width = null, $height = null) {
        // Stub
        return array('width' => 0, 'height' => 0);
    }
    
    // Additional stub methods that might be called
    protected function getReader($file = null) {
        return null;
    }
    
    protected function releaseReader($readerId) {
        // Stub
    }
}