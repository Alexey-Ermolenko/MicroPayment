<?php

namespace App\Message;

final class TransactionCreated extends AbstractTransactionEvent
{
    public function name(): string
    {
        return 'TransactionCreated';
    }
}
