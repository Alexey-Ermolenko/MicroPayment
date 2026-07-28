<?php

namespace App\Service;

use App\Message\BlockTransaction;
use App\Repository\TransactionRepository;
use DateTimeImmutable;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Blocks transactions nobody approved or blocked in time. The command travels the same
 * pipeline as an admin block, so the audit log and notifications come for free.
 */
final readonly class TransactionExpiryService
{
    public function __construct(
        private MessageBusInterface $commandBus,
        private TransactionRepository $transactions,
        private string $expireAfter = '-3 days',
    ) {
    }

    /**
     * @throws ExceptionInterface
     * @throws \DateMalformedStringException
     */
    public function expire(): int
    {
        $stale = $this->transactions->findPendingOlderThan(new DateTimeImmutable($this->expireAfter));

        foreach ($stale as $transaction) {
            $this->commandBus->dispatch(new BlockTransaction((string) $transaction->getId()));
        }

        return count($stale);
    }
}
