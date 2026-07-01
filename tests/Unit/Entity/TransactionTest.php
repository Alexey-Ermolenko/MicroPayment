<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Transaction;
use App\Enum\TransactionStatus;
use App\Enum\TransactionType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class TransactionTest extends TestCase
{
    public function testIsPendingWhenCreated(): void
    {
        $tx = new Transaction(Uuid::v4(), TransactionType::DEPOSIT, 1000, 'USD');

        self::assertSame(TransactionStatus::PENDING, $tx->getStatus());
        self::assertSame(TransactionType::DEPOSIT, $tx->getType());
        self::assertSame(1000, $tx->getAmount());
    }

    public function testStatusCanTransition(): void
    {
        $tx = new Transaction(Uuid::v4(), TransactionType::TRANSFER, 1000, 'USD');

        $tx->setStatus(TransactionStatus::APPROVED);

        self::assertSame(TransactionStatus::APPROVED, $tx->getStatus());
    }
}