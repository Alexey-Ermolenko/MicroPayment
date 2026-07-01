<?php

namespace App\Message;

final class TransactionBlocked extends AbstractTransactionEvent
{
    public function name(): string
    {
        return 'TransactionBlocked';
    }
}
