<?php
/**
 * Stub for PSR-3 LoggerAwareInterface
 */

namespace Psr\Log;

interface LoggerAwareInterface {
    /**
     * Sets a logger instance on the object.
     *
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger);
}