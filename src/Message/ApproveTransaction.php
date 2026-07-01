<?php

namespace App\Message;

final readonly class ApproveTransaction implements Command
{
    public function __construct(
        public string $transactionId,
    ) {
    }
}
