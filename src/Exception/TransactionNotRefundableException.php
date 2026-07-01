<?php

namespace App\Exception;

final class TransactionNotRefundableException extends DomainException
{
    public function statusCode(): int
    {
        return 409;
    }
}
