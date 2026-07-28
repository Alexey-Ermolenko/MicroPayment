<?php

namespace App\Repository;

use App\Entity\Transaction;
use App\Entity\User;
use App\Enum\TransactionStatus;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Transaction>
 */
class TransactionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Transaction::class);
    }

    public function findOneByIdempotencyKey(string $key): ?Transaction
    {
        return $this->findOneBy(['idempotencyKey' => $key]);
    }

    public function findOneRefundOf(Transaction $original): ?Transaction
    {
        return $this->findOneBy(['refundedTransaction' => $original]);
    }

    /**
     * @return Transaction[]
     */
    public function findPendingOlderThan(DateTimeImmutable $threshold, int $limit = 500): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.status = :pending')
            ->andWhere('t.createdAt < :threshold')
            ->setParameter('pending', TransactionStatus::PENDING)
            ->setParameter('threshold', $threshold)
            ->orderBy('t.createdAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Transaction[]
     */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('t')
            ->leftJoin('t.senderWallet', 's')
            ->leftJoin('t.recipientWallet', 'r')
            ->where('s.user = :user OR r.user = :user')
            ->setParameter('user', $user)
            ->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
