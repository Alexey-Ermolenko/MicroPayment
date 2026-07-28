<?php

namespace App\Tests\Service;

use App\Entity\Transaction;
use App\Entity\User;
use App\Entity\Wallet;
use App\Enum\TransactionStatus;
use App\Enum\TransactionType;
use App\Message\CreateTransaction;
use App\Service\TransactionExpiryService;
use App\Service\TransactionService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;
use Throwable;

final class TransactionExpiryServiceTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private TransactionService $transactions;
    private TransactionExpiryService $expiry;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $this->em = $container->get(EntityManagerInterface::class);
        $this->transactions = $container->get(TransactionService::class);
        $this->expiry = $container->get(TransactionExpiryService::class);
    }

    /**
     * @throws Throwable
     */
    public function testStalePendingIsBlocked(): void
    {
        $transaction = $this->makePending();
        $this->age($transaction, '-4 days');

        self::assertGreaterThanOrEqual(1, $this->expiry->expire());
        self::assertSame(TransactionStatus::BLOCKED, $this->reload($transaction)->getStatus());
    }

    /**
     * @throws Throwable
     */
    public function testFreshPendingIsLeftAlone(): void
    {
        $transaction = $this->makePending();

        $this->expiry->expire();

        self::assertSame(TransactionStatus::PENDING, $this->reload($transaction)->getStatus());
    }

    /**
     * @throws Throwable
     */
    public function testBlockedTransactionIsNotTouchedAgain(): void
    {
        $transaction = $this->makePending();
        $this->age($transaction, '-4 days');
        $this->expiry->expire();

        // A second run must not find it any more: it is no longer PENDING.
        $this->age($transaction, '-4 days');

        self::assertSame(0, $this->expiry->expire());
        self::assertSame(TransactionStatus::BLOCKED, $this->reload($transaction)->getStatus());
    }

    /**
     * @throws Throwable
     */
    private function makePending(): Transaction
    {
        return $this->transactions->create(new CreateTransaction(
            transactionId: (string) Uuid::v4(),
            type: TransactionType::DEPOSIT->value,
            amount: 500,
            currency: 'USD',
            recipientWalletId: (string) $this->makeWallet()->getId(),
        ));
    }

    /** Moves created_at into the past: the entity has no setter, and it should not get one. */
    private function age(Transaction $transaction, string $shift): void
    {
        $this->em->createQuery('UPDATE App\Entity\Transaction t SET t.createdAt = :date WHERE t.id = :id')
            ->setParameter('date', new DateTimeImmutable($shift))
            ->setParameter('id', $transaction->getId(), 'uuid')
            ->execute();
        $this->em->clear();
    }

    private function reload(Transaction $transaction): Transaction
    {
        $this->em->clear();

        return $this->em->find(Transaction::class, $transaction->getId());
    }

    private function makeWallet(): Wallet
    {
        $user = new User(uniqid('expiry_', true).'@example.com');
        $user->setPassword('x');
        $this->em->persist($user);

        $wallet = new Wallet($user, 'USD');
        $this->em->persist($wallet);
        $this->em->flush();

        return $wallet;
    }
}
