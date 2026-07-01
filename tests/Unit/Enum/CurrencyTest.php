<?php

namespace App\Tests\Unit\Enum;

use App\Enum\Currency;
use PHPUnit\Framework\TestCase;

final class CurrencyTest extends TestCase
{
    public function testValuesReturnsEveryCaseValue(): void
    {
        self::assertSame(['USD', 'EUR', 'RUB', 'GBP', 'JPY'], Currency::values());
    }
}