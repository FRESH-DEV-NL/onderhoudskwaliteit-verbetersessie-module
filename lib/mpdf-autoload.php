<?php
/**
 * Simple autoloader for mPDF library
 */

// Register autoloader for mPDF and setasign
spl_autoload_register(function ($class) {
    // Check if this is an mPDF class
    if (strpos($class, 'Mpdf\\') === 0) {
        // Remove the namespace prefix
        $relativeClass = substr($class, 5);
        
        // Convert namespace separators to directory separators
        $file = OVM_PLUGIN_DIR . 'lib/mpdf/src/' . str_replace('\\', '/', $relativeClass) . '.php';
        
        // If the file exists, require it
        if (file_exists($file)) {
            require_once $file;
        }
    }
    // Check if this is a setasign class (FPDI stub)
    elseif (strpos($class, 'setasign\\') === 0) {
        // Convert namespace separators to directory separators
        $file = OVM_PLUGIN_DIR . 'lib/mpdf/src/' . str_replace('\\', '/', $class) . '.php';
        
        // If the file exists, require it
        if (file_exists($file)) {
            require_once $file;
        }
    }
    // Check if this is a Psr class (PSR-3 stubs)
    elseif (strpos($class, 'Psr\\') === 0) {
        // Convert namespace separators to directory separators
        $file = OVM_PLUGIN_DIR . 'lib/mpdf/src/' . str_replace('\\', '/', $class) . '.php';
        
        // If the file exists, require it
        if (file_exists($file)) {
            require_once $file;
        }
    }
});

// Load required traits and interfaces first
require_once(OVM_PLUGIN_DIR . 'lib/mpdf/src/Strict.php');
require_once(OVM_PLUGIN_DIR . 'lib/mpdf/src/setasign/Fpdi/FpdiTrait.php');
require_once(OVM_PLUGIN_DIR . 'lib/mpdf/src/setasign/Fpdi/PdfReader/PageBoundaries.php');
require_once(OVM_PLUGIN_DIR . 'lib/mpdf/src/Mpdf/PsrLogAwareTrait/MpdfPsrLogAwareTrait.php');
require_once(OVM_PLUGIN_DIR . 'lib/mpdf/src/Mpdf/PsrLogAwareTrait/PsrLogAwareTrait.php');

// Only load PSR interfaces if they don't already exist
if (!interface_exists('Psr\Log\LoggerInterface')) {
    require_once(OVM_PLUGIN_DIR . 'lib/mpdf/src/Psr/Log/LoggerInterface.php');
}
if (!interface_exists('Psr\Log\LoggerAwareInterface')) {
    require_once(OVM_PLUGIN_DIR . 'lib/mpdf/src/Psr/Log/LoggerAwareInterface.php');
}
if (!class_exists('Psr\Log\NullLogger')) {
    require_once(OVM_PLUGIN_DIR . 'lib/mpdf/src/Psr/Log/NullLogger.php');
}

// Load required base classes
require_once(OVM_PLUGIN_DIR . 'lib/mpdf/src/Mpdf.php');
require_once(OVM_PLUGIN_DIR . 'lib/mpdf/src/MpdfException.php');
require_once(OVM_PLUGIN_DIR . 'lib/mpdf/src/Output/Destination.php');

// Load PSR HTTP Message shims to prevent fatal errors
if (!class_exists('Mpdf\PsrHttpMessageShim\Request')) {
    require_once(OVM_PLUGIN_DIR . 'lib/mpdf/src/Mpdf/PsrHttpMessageShim/Request.php');
}
if (!class_exists('Mpdf\PsrHttpMessageShim\Response')) {
    require_once(OVM_PLUGIN_DIR . 'lib/mpdf/src/Mpdf/PsrHttpMessageShim/Response.php');
}