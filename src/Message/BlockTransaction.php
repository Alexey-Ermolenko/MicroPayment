<?php

namespace App\Message;

final readonly class BlockTransaction implements Command
{
    public function __construct(public string $transactionId)
    {
    }
}
