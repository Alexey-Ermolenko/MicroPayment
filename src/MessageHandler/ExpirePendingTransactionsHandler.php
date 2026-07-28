<?php

namespace App\MessageHandler;

use App\Message\ExpirePendingTransactions;
use App\Service\TransactionExpiryService;
use DateMalformedStringException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\ExceptionInterface;

#[AsMessageHandler]
final readonly class ExpirePendingTransactionsHandler
{
    public function __construct(
        private TransactionExpiryService $expiry
    ) {
    }

    /**
     * @throws ExceptionInterface
     * @throws DateMalformedStringException
     */
    public function __invoke(ExpirePendingTransactions $message): void
    {
        $this->expiry->expire();
    }
}
