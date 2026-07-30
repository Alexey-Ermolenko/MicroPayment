<?php

namespace App\MessageHandler;

use App\Message\ApproveTransaction;
use App\Repository\TransactionRepository;
use App\Service\TransactionService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Throwable;

#[AsMessageHandler]
final readonly class ApproveTransactionHandler
{
    public function __construct(
        private TransactionService $transactions,
        private TransactionRepository $repository,
    ) {
    }

    /**
     * @throws Throwable
     */
    public function __invoke(ApproveTransaction $command): void
    {
        if (null !== $transaction = $this->repository->find($command->transactionId)) {
            $this->transactions->approve($transaction, $command->actor);
        }
    }
}
