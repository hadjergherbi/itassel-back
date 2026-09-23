<?php

namespace App\Exceptions;

use RuntimeException;

class ConflitMetier extends RuntimeException
{
    public function __construct(
        public readonly string $codeErreur,
        string $message,
    ) {
        parent::__construct($message);
    }
}
