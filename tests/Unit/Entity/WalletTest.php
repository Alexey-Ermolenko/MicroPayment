<?php

namespace App\Tests\Unit\Entity;

use App\Entity\User;
use App\Entity\Wallet;
use PHPUnit\Framework\TestCase;

final class WalletTest extends TestCase
{
    public function testCreditIncreasesBalance(): void
    {
        $wallet = new Wallet(new User('owner@example.com'));

        $wallet->credit(5000);
        $wallet->credit(2500);

        self::assertSame(7500, $wallet->getBalance());
    }

    public function testDebitDecreasesBalance(): void
    {
        $wallet = new Wallet(new User('owner@example.com'));
        $wallet->credit(5000);

        $wallet->debit(2000);

        self::assertSame(3000, $wallet->getBalance());
    }
}