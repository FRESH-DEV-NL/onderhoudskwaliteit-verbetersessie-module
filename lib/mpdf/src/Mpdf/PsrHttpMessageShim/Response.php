<?php

namespace Mpdf\PsrHttpMessageShim;

/**
 * Stub Response class to prevent fatal errors
 * This is a minimal implementation to satisfy mPDF's AssetFetcher
 */
class Response
{
    private $statusCode;
    private $body;
    
    public function __construct($statusCode = 404, $body = '')
    {
        $this->statusCode = $statusCode;
        $this->body = $body;
    }
    
    public function getStatusCode()
    {
        return $this->statusCode;
    }
    
    public function getBody()
    {
        return $this->body;
    }
}