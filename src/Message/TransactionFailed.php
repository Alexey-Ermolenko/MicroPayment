<?php

namespace App\Message;

final class TransactionFailed extends AbstractTransactionEvent
{
    public function name(): string
    {
        return 'TransactionFailed';
    }
}
