<?php

namespace App\Exception;

final class InvalidTransactionException extends DomainException
{
    public function statusCode(): int
    {
        return 400;
    }
}
