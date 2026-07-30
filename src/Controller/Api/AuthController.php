<?php

namespace App\Controller\Api;

use App\Dto\RegisterRequest;
use App\Entity\User;
use App\Repository\TransactionRepository;
use App\Repository\UserRepository;
use App\Repository\WalletRepository;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1')]
final class AuthController extends AbstractController
{
    #[Route('/register', methods: ['POST'])]
    public function register(
        #[MapRequestPayload] RegisterRequest $request,
        UserRepository $users,
        UserPasswordHasherInterface $hasher,
        EntityManagerInterface $em,
    ): JsonResponse {
        if (null !== $users->findOneByEmail($request->email)) {
            return $this->json(['error' => 'Email already registered.'], 409);
        }

        // Registration always creates a plain user: ROLE_ADMIN is seeded by migration, never self-assigned.
        $user = new User($request->email);
        $user->setPassword($hasher->hashPassword($user, $request->password));
        $user->setRoles(['ROLE_USER']);
        $em->persist($user);
        $em->flush();

        return $this->json([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
        ], 201);
    }

    /** Authentication is handled by the json_login firewall; this route only exists for routing. */
    #[Route('/login', methods: ['POST'])]
    public function login(): JsonResponse
    {
        throw new LogicException('This endpoint is handled by the security firewall.');
    }

    #[Route('/profile', methods: ['GET'])]
    public function profile(TransactionRepository $transactions, WalletRepository $wallets): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $items = [];
        $userTransactions = $transactions->findByUser($user);
        foreach ($userTransactions as $transaction) {
            $items[] = [
                'id' => $transaction->getId(),
                'type' => $transaction->getType()->value,
                'status' => $transaction->getStatus()->value,
                'amount' => $transaction->getAmount(),
                'currency' => $transaction->getCurrency(),
                'senderWalletId' => $transaction->getSenderWallet()?->getId()?->toRfc4122(),
                'recipientWalletId' => $transaction->getRecipientWallet()?->getId()?->toRfc4122(),
                'createdAt' => $transaction->getCreatedAt()->format(\DATE_ATOM),
            ];
        }

        $walletItems = [];
        $userWallets = $wallets->findBy(['user' => $user], ['createdAt' => 'DESC']);
        foreach ($userWallets as $wallet) {
            $walletItems[] = [
                'id' => $wallet->getId(),
                'currency' => $wallet->getCurrency(),
                'balance' => $wallet->getBalance(),
            ];
        }

        return $this->json([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
            'wallets' => $walletItems,
            'transactions' => $items,
        ]);
    }
}
