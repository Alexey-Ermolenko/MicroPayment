<?php

namespace App\MessageHandler;

use App\Message\CreateTransaction;
use App\Service\TransactionService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CreateTransactionHandler
{
    public function __construct(
        private TransactionService $transactions
    ) {
    }

    public function __invoke(CreateTransaction $command): void
    {
        $this->transactions->create($command);
    }
}
