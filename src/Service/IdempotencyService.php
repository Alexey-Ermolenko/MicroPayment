<?php

namespace App\Service;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;

/**
 * Stores Idempotency-Key -> Transaction id in Redis so a repeated request
 * returns the original transaction instead of creating a duplicate.
 */
final readonly class IdempotencyService
{
    public function __construct(
        private CacheItemPoolInterface $idempotencyCache,
    ) {
    }

    /**
     * @throws InvalidArgumentException
     */
    public function get(string $key): ?string
    {
        $item = $this->idempotencyCache->getItem($this->normalize($key));

        return $item->isHit() ? $item->get() : null;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function store(string $key, string $transactionId): void
    {
        $item = $this->idempotencyCache->getItem($this->normalize($key));
        $item->set($transactionId);
        $this->idempotencyCache->save($item);
    }

    private function normalize(string $key): string
    {
        return 'idem_'.preg_replace('/[^A-Za-z0-9_.]/', '_', $key);
    }
}
