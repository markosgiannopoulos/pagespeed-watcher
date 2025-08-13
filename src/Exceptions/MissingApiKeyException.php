<?php

namespace Apogee\Watcher\Exceptions;

use Exception;

/**
 * Exception thrown when the PageSpeed Insights API key is not configured.
 * 
 * This exception is thrown when attempting to make API requests without
 * a valid API key configured in the application.
 */
class MissingApiKeyException extends Exception
{
    /**
     * Create a new missing API key exception instance.
     * 
     * @param string $message The error message (defaults to a helpful message)
     */
    public function __construct(string $message = 'PSI API key is not configured')
    {
        parent::__construct($message);
    }
}