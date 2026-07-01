<?php

namespace App\Message;

final class TransactionApproved extends AbstractTransactionEvent
{
    public function name(): string
    {
        return 'TransactionApproved';
    }
}
