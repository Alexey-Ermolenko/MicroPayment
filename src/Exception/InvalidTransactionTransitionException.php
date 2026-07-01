<?php

namespace App\Exception;

final class InvalidTransactionTransitionException extends DomainException
{
    public function statusCode(): int
    {
        return 409;
    }
}
