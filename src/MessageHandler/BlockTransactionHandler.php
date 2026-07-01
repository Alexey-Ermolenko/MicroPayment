<?php

namespace App\MessageHandler;

use App\Message\BlockTransaction;
use App\Repository\TransactionRepository;
use App\Service\TransactionService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class BlockTransactionHandler
{
    public function __construct(
        private TransactionService $transactions,
        private TransactionRepository $repository,
    ) {
    }

    public function __invoke(BlockTransaction $command): void
    {
        if (null !== $transaction = $this->repository->find($command->transactionId)) {
            $this->transactions->block($transaction);
        }
    }
}
