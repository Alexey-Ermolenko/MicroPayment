<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\Wallet;
use Doctrine\ORM\EntityManagerInterface;

final readonly class WalletService
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    public function createWallet(User $user, string $currency = 'USD'): Wallet
    {
        $wallet = new Wallet($user, $currency);
        $this->em->persist($wallet);
        $this->em->flush();

        return $wallet;
    }
}
