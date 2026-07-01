<?php

namespace App\Entity;

use App\Enum\TransactionStatus;
use App\Enum\TransactionType;
use App\Repository\TransactionRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: TransactionRepository::class)]
#[ORM\Table(name: 'transactions')]
#[ORM\Index(name: 'idx_transaction_status', columns: ['status'])]
#[ORM\Index(name: 'idx_transaction_created_at', columns: ['created_at'])]
#[ORM\Index(name: 'idx_transaction_type', columns: ['type'])]
class Transaction
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 128, nullable: true, unique: true)]
    private ?string $idempotencyKey = null;

    #[ORM\Column(enumType: TransactionType::class)]
    private TransactionType $type;

    #[ORM\Column(enumType: TransactionStatus::class)]
    private TransactionStatus $status = TransactionStatus::PENDING;

    #[ORM\ManyToOne(targetEntity: Wallet::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Wallet $senderWallet = null;

    #[ORM\ManyToOne(targetEntity: Wallet::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Wallet $recipientWallet = null;

    #[ORM\Column(type: 'bigint')]
    private int $amount;

    #[ORM\Column(length: 3)]
    private string $currency;

    #[ORM\ManyToOne(targetEntity: Transaction::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Transaction $refundedTransaction = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    public function __construct(Uuid $id, TransactionType $type, int $amount, string $currency)
    {
        $this->id = $id;
        $this->type = $type;
        $this->amount = $amount;
        $this->currency = $currency;
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function setIdempotencyKey(?string $key): static
    {
        $this->idempotencyKey = $key;

        return $this;
    }

    public function getType(): TransactionType
    {
        return $this->type;
    }

    public function getStatus(): TransactionStatus
    {
        return $this->status;
    }

    public function setStatus(TransactionStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getSenderWallet(): ?Wallet
    {
        return $this->senderWallet;
    }

    public function setSenderWallet(?Wallet $wallet): static
    {
        $this->senderWallet = $wallet;

        return $this;
    }

    public function getRecipientWallet(): ?Wallet
    {
        return $this->recipientWallet;
    }

    public function setRecipientWallet(?Wallet $wallet): static
    {
        $this->recipientWallet = $wallet;

        return $this;
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getRefundedTransaction(): ?self
    {
        return $this->refundedTransaction;
    }

    public function setRefundedTransaction(?self $transaction): static
    {
        $this->refundedTransaction = $transaction;

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
