<?php

namespace App\Entity;

use App\Repository\WalletRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: WalletRepository::class)]
#[ORM\Table(name: 'wallets')]
#[ORM\Index(name: 'idx_wallet_user', columns: ['user_id'])]
class Wallet
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\Column(length: 3)]
    private string $currency;

    /** Balance in minor units (cents). */
    #[ORM\Column(type: 'bigint')]
    private int $balance = 0;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    public function __construct(User $user, string $currency = 'USD')
    {
        $this->id = Uuid::v4();
        $this->user = $user;
        $this->currency = $currency;
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getBalance(): int
    {
        return $this->balance;
    }

    public function credit(int $amount): void
    {
        $this->balance += $amount;
    }

    public function debit(int $amount): void
    {
        $this->balance -= $amount;
    }
}
