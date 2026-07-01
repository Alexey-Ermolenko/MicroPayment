<?php

namespace App\Message;

abstract class AbstractTransactionEvent implements DomainEvent
{
    public function __construct(
        public readonly string $transactionId,
        public readonly string $type,
        public readonly int $amount,
        public readonly string $currency,
        public readonly ?string $senderUserId = null,
        public readonly ?string $recipientUserId = null,
        public readonly ?string $reason = null,
    ) {
    }

    public function toArray(): array
    {
        return [
            'transactionId' => $this->transactionId,
            'type' => $this->type,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'senderUserId' => $this->senderUserId,
            'recipientUserId' => $this->recipientUserId,
            'reason' => $this->reason,
        ];
    }
}
