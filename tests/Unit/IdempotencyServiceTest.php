<?php

namespace App\Tests\Unit;

use App\Service\IdempotencyService;
use PHPUnit\Framework\TestCase;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class IdempotencyServiceTest extends TestCase
{
    /**
     * @throws InvalidArgumentException
     */
    public function testUnknownKeyReturnsNull(): void
    {
        $service = new IdempotencyService(new ArrayAdapter());

        self::assertNull($service->get('missing-key'));
    }

    /**
     * @throws InvalidArgumentException
     */
    public function testStoredKeyReturnsSameTransactionId(): void
    {
        $service = new IdempotencyService(new ArrayAdapter());
        $service->store('key-1', 'transaction-123');

        self::assertSame('transaction-123', $service->get('key-1'));
    }
}
