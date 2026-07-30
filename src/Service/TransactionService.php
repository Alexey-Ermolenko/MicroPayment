<?php

namespace App\Service;

use App\Entity\Transaction;
use App\Entity\Wallet;
use App\Enum\TransactionStatus;
use App\Enum\TransactionType;
use App\Exception\InsufficientFundsException;
use App\Exception\InvalidTransactionException;
use App\Exception\InvalidTransactionTransitionException;
use App\Message\AbstractTransactionEvent;
use App\Message\CreateTransaction;
use App\Message\TransactionApproved;
use App\Message\TransactionBlocked;
use App\Message\TransactionCreated;
use App\Message\TransactionFailed;
use App\Repository\TransactionRepository;
use App\Repository\WalletRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;
use Throwable;

/**
 * Applies transaction commands consumed from Kafka: create records a PENDING row,
 * approve/block settle it. Money moves only on approve.
 */
final readonly class TransactionService
{
    public function __construct(
        private EntityManagerInterface $em,
        private ManagerRegistry $registry,
        private MessageBusInterface $eventBus,
        private TransactionRepository $transactions,
        private WalletRepository $wallets,
    ) {
    }

    /**
     * @throws ExceptionInterface
     */
    public function create(CreateTransaction $command): Transaction
    {
        $id = Uuid::fromString($command->transactionId);
        if (null !== $existing = $this->transactions->find($id)) {
            return $existing;
        }

        $type = TransactionType::from($command->type);

        if (TransactionType::REFUND === $type) {
            $original = $this->transactions->find($command->refundedTransactionId)
                ?? throw new InvalidTransactionException('Original transaction not found.');
            $transaction = new Transaction($id, $type, $original->getAmount(), $original->getCurrency());
            $transaction->setSenderWallet($original->getRecipientWallet());
            $transaction->setRecipientWallet($original->getSenderWallet());
            $transaction->setRefundedTransaction($original);
        } else {
            $transaction = new Transaction($id, $type, $command->amount, $command->currency);
            $transaction->setSenderWallet($this->wallet($command->senderWalletId));
            $transaction->setRecipientWallet($this->wallet($command->recipientWalletId));
        }

        $transaction->setIdempotencyKey($command->idempotencyKey);
        $this->em->persist($transaction);
        $this->em->flush();

        $this->eventBus->dispatch($this->event(TransactionCreated::class, $transaction));

        return $transaction;
    }

    /**
     * @throws Throwable
     */
    public function approve(Transaction $transaction, ?string $actor = null): Transaction
    {
        $this->assertPending($transaction, 'approve');

        $sender = $transaction->getSenderWallet();
        $recipient = $transaction->getRecipientWallet();
        $amount = $transaction->getAmount();

        try {
            $this->em->wrapInTransaction(function () use ($transaction, $sender, $recipient, $amount): void {
                if (null !== $sender) {
                    $this->em->lock($sender, LockMode::PESSIMISTIC_WRITE);
                    if ($sender->getBalance() < $amount) {
                        throw new InsufficientFundsException();
                    }
                    $sender->debit($amount);
                }

                if (null !== $recipient) {
                    $this->em->lock($recipient, LockMode::PESSIMISTIC_WRITE);
                    $recipient->credit($amount);
                }

                $transaction->setStatus(TransactionStatus::APPROVED);
            });
        } catch (InsufficientFundsException $e) {
            // Only a business rejection settles as FAILED. Infrastructure errors (deadlock, lock timeout,
            // lost connection) propagate so Messenger retries the command instead of losing the money move.
            return $this->markFailed($transaction->getId(), $e->getMessage(), $actor);
        }

        $this->eventBus->dispatch($this->event(TransactionApproved::class, $transaction, actor: $actor));

        return $transaction;
    }

    /**
     * @throws ExceptionInterface
     */
    public function block(Transaction $transaction, ?string $actor = null): Transaction
    {
        $this->assertPending($transaction, 'block');

        $transaction->setStatus(TransactionStatus::BLOCKED);
        $this->em->flush();

        $this->eventBus->dispatch($this->event(TransactionBlocked::class, $transaction, actor: $actor));

        return $transaction;
    }

    /**
     * Mark FAILED on a fresh manager: the approve transaction rolled back and closed the previous one.
     *
     * @throws ExceptionInterface
     */
    private function markFailed(Uuid $id, string $reason, ?string $actor = null): Transaction
    {
        $this->registry->resetManager();
        /** @var EntityManagerInterface $em */
        $em = $this->registry->getManager();

        /** @var Transaction $transaction */
        $transaction = $em->find(Transaction::class, $id);
        $transaction->setStatus(TransactionStatus::FAILED);
        $em->flush();

        $this->eventBus->dispatch($this->event(TransactionFailed::class, $transaction, $reason, $actor));

        return $transaction;
    }

    private function assertPending(Transaction $transaction, string $action): void
    {
        if (TransactionStatus::PENDING !== $transaction->getStatus()) {
            throw new InvalidTransactionTransitionException(sprintf('Cannot %s a %s transaction.', $action, $transaction->getStatus()->value));
        }
    }

    /**
     * @param class-string<AbstractTransactionEvent> $class
     */
    private function event(string $class, Transaction $transaction, ?string $reason = null, ?string $actor = null): AbstractTransactionEvent
    {
        return new $class(
            (string) $transaction->getId(),
            $transaction->getType()->value,
            $transaction->getAmount(),
            $transaction->getCurrency(),
            $this->userId($transaction->getSenderWallet()),
            $this->userId($transaction->getRecipientWallet()),
            $reason,
            $actor,
        );
    }

    private function wallet(?string $id): ?Wallet
    {
        return null !== $id ? $this->wallets->find($id) : null;
    }

    private function userId(?Wallet $wallet): ?string
    {
        return null !== $wallet ? (string) $wallet->getUser()->getId() : null;
    }
}
