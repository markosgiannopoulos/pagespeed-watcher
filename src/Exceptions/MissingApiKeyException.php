<?php

namespace Apogee\Watcher\Exceptions;

use Exception;

class MissingApiKeyException extends Exception
{
    public function __construct(string $message = 'PSI API key is not configured')
    {
        parent::__construct($message);
    }
}