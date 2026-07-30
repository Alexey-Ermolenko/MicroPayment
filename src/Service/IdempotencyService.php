<?php

namespace App\Service;

use Redis;
use RedisException;

/**
 * Maps Idempotency-Key -> Transaction id in Redis so a repeated request returns the original
 * transaction instead of creating a duplicate. The claim is atomic (SET NX), so two parallel
 * requests with the same key cannot both win it. Keys arrive already scoped to a user
 * (see TransactionController::idempotencyKey()).
 */
final readonly class IdempotencyService
{
    public function __construct(
        private Redis $redis,
        private int $idempotencyTtl,
    ) {
    }

    /**
     * Claims the key for $transactionId and returns the id that owns it: ours, or the one a
     * concurrent request stored first.
     *
     * @throws RedisException
     */
    public function reserve(string $key, string $transactionId): string
    {
        $normalized = $this->normalize($key);

        if (true === $this->redis->set($normalized, $transactionId, ['nx', 'ex' => $this->idempotencyTtl])) {
            return $transactionId;
        }

        $owner = $this->redis->get($normalized);

        return is_string($owner) && '' !== $owner ? $owner : $transactionId;
    }

    /**
     * Drops the claim, so a key whose command never reached Kafka does not point at a transaction
     * that will never exist.
     *
     * @throws RedisException
     */
    public function release(string $key): void
    {
        $this->redis->del($this->normalize($key));
    }

    private function normalize(string $key): string
    {
        return 'idem_'.preg_replace('/[^A-Za-z0-9_.]/', '_', $key);
    }
}
