<?php

namespace App\Message;

final readonly class CreateTransaction implements Command
{
    public function __construct(
        public string $transactionId,
        public string $type,
        public int $amount,
        public string $currency,
        public ?string $senderWalletId = null,
        public ?string $recipientWalletId = null,
        public ?string $idempotencyKey = null,
        public ?string $refundedTransactionId = null,
    ) {
    }
}
