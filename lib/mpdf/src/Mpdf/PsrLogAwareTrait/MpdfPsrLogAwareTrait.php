<?php
/**
 * Stub for PSR Log trait
 */

namespace Mpdf\PsrLogAwareTrait;

trait MpdfPsrLogAwareTrait {
    // Stub implementation - we don't need PSR logging for this plugin
    
    protected $logger;
    
    public function setLogger($logger) {
        $this->logger = $logger;
    }
}