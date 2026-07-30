<?php

namespace App\Tests\Service;

use App\Service\IdempotencyService;
use RedisException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Runs against the real Redis of the stack: the claim has to be atomic, which an array cache cannot show.
 */
final class IdempotencyServiceTest extends KernelTestCase
{
    private IdempotencyService $service;
    private string $key;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->service = self::getContainer()->get(IdempotencyService::class);
        $this->key = uniqid('test-key-', true);
    }

    protected function tearDown(): void
    {
        $this->service->release($this->key);
        parent::tearDown();
    }

    /**
     * @throws RedisException
     */
    public function testFreeKeyIsClaimedByTheCaller(): void
    {
        self::assertSame('transaction-1', $this->service->reserve($this->key, 'transaction-1'));
    }

    /**
     * @throws RedisException
     */
    public function testSecondClaimReturnsTheWinningTransactionId(): void
    {
        $this->service->reserve($this->key, 'transaction-1');

        self::assertSame('transaction-1', $this->service->reserve($this->key, 'transaction-2'));
    }

    /**
     * @throws RedisException
     */
    public function testReleasedKeyCanBeClaimedAgain(): void
    {
        $this->service->reserve($this->key, 'transaction-1');
        $this->service->release($this->key);

        self::assertSame('transaction-2', $this->service->reserve($this->key, 'transaction-2'));
    }
}
