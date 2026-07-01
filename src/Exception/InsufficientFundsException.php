<?php

namespace App\Exception;

final class InsufficientFundsException extends DomainException
{
    public function __construct(string $message = 'Insufficient funds.')
    {
        parent::__construct($message);
    }

    public function statusCode(): int
    {
        return 422;
    }
}
