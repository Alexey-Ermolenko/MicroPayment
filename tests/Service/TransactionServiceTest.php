<?php

namespace App\Tests\Service;

use App\Entity\Transaction;
use App\Entity\User;
use App\Entity\Wallet;
use App\Enum\TransactionStatus;
use App\Enum\TransactionType;
use App\Message\CreateTransaction;
use App\Service\TransactionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;
use Throwable;

final class TransactionServiceTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private TransactionService $service;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $this->em = $container->get(EntityManagerInterface::class);
        $this->service = $container->get(TransactionService::class);
    }

    /**
     * @throws Throwable
     */
    public function testDepositCreditsOnApprove(): void
    {
        $wallet = $this->makeWallet();

        $tx = $this->create(TransactionType::DEPOSIT, 5000, recipient: $wallet);
        self::assertSame(TransactionStatus::PENDING, $tx->getStatus());
        self::assertSame(0, $wallet->getBalance());

        $this->service->approve($tx);

        self::assertSame(TransactionStatus::APPROVED, $tx->getStatus());
        self::assertSame(5000, $wallet->getBalance());
    }

    /**
     * @throws Throwable
     */
    public function testWithdrawalDebitsOnApprove(): void
    {
        $wallet = $this->makeWallet(8000);

        $tx = $this->create(TransactionType::WITHDRAWAL, 3000, sender: $wallet);
        $this->service->approve($tx);

        self::assertSame(TransactionStatus::APPROVED, $tx->getStatus());
        self::assertSame(5000, $wallet->getBalance());
    }

    /**
     * @throws Throwable
     */
    public function testTransferMovesFundsOnApprove(): void
    {
        $sender = $this->makeWallet(10000);
        $recipient = $this->makeWallet();

        $tx = $this->create(TransactionType::TRANSFER, 3000, sender: $sender, recipient: $recipient);
        $this->service->approve($tx);

        self::assertSame(7000, $sender->getBalance());
        self::assertSame(3000, $recipient->getBalance());
    }

    /**
     * @throws Throwable
     */
    public function testInsufficientFundsMarksFailed(): void
    {
        $sender = $this->makeWallet(100);
        $recipient = $this->makeWallet();
        $senderId = $sender->getId();

        $tx = $this->create(TransactionType::TRANSFER, 5000, sender: $sender, recipient: $recipient);
        $result = $this->service->approve($tx);

        self::assertSame(TransactionStatus::FAILED, $result->getStatus());
        $em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertSame(100, $em->find(Wallet::class, $senderId)->getBalance());
    }

    /**
     * @throws Throwable
     */
    public function testRefundReversesTransfer(): void
    {
        $sender = $this->makeWallet(10000);
        $recipient = $this->makeWallet();
        $transfer = $this->create(TransactionType::TRANSFER, 4000, sender: $sender, recipient: $recipient);
        $this->service->approve($transfer);

        $refund = $this->service->create(new CreateTransaction(
            transactionId: (string) Uuid::v4(),
            type: TransactionType::REFUND->value,
            amount: 0,
            currency: 'USD',
            refundedTransactionId: (string) $transfer->getId(),
        ));
        $this->service->approve($refund);

        self::assertSame(TransactionType::REFUND, $refund->getType());
        self::assertSame(TransactionStatus::APPROVED, $refund->getStatus());
        self::assertSame(10000, $sender->getBalance());
        self::assertSame(0, $recipient->getBalance());
    }

    public function testBlockLeavesBalanceUntouched(): void
    {
        $wallet = $this->makeWallet(1000);

        $tx = $this->create(TransactionType::WITHDRAWAL, 400, sender: $wallet);
        $this->service->block($tx);

        self::assertSame(TransactionStatus::BLOCKED, $tx->getStatus());
        self::assertSame(1000, $wallet->getBalance());
    }

    private function create(TransactionType $type, int $amount, ?Wallet $sender = null, ?Wallet $recipient = null): Transaction
    {
        return $this->service->create(new CreateTransaction(
            transactionId: (string) Uuid::v4(),
            type: $type->value,
            amount: $amount,
            currency: 'USD',
            senderWalletId: null !== $sender ? (string) $sender->getId() : null,
            recipientWalletId: null !== $recipient ? (string) $recipient->getId() : null,
        ));
    }

    private function makeWallet(int $balance = 0): Wallet
    {
        $user = new User(uniqid('user_', true).'@example.com');
        $user->setPassword('x');
        $wallet = new Wallet($user);
        if ($balance > 0) {
            $wallet->credit($balance);
        }
        $this->em->persist($user);
        $this->em->persist($wallet);
        $this->em->flush();

        return $wallet;
    }
}
