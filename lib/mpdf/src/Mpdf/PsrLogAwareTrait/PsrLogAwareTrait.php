<?php
/**
 * Stub for PSR Log Aware trait
 */

namespace Mpdf\PsrLogAwareTrait;

trait PsrLogAwareTrait {
    // Stub implementation - we don't need PSR logging for this plugin
    
    protected $logger;
    
    public function setLogger($logger) {
        $this->logger = $logger;
    }
}