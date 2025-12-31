<?php

namespace App\Exceptions;

use RuntimeException;

class InsufficientTokensException extends RuntimeException
{
    public function __construct(public readonly int $required, public readonly int $available)
    {
        parent::__construct(
            sprintf('Insufficient tokens: required %d, available %d', $required, $available)
        );
    }
}
