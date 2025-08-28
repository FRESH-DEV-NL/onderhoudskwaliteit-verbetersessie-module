<?php

namespace Mpdf\PsrHttpMessageShim;

/**
 * Stub Request class to prevent fatal errors
 * This is a minimal implementation to satisfy mPDF's AssetFetcher
 */
class Request
{
    private $method;
    private $uri;
    
    public function __construct($method, $uri)
    {
        $this->method = $method;
        $this->uri = $uri;
    }
    
    public function getMethod()
    {
        return $this->method;
    }
    
    public function getUri()
    {
        return $this->uri;
    }
}