<?php

namespace App\Controller\Api;

use App\Dto\WalletRequest;
use App\Entity\User;
use App\Entity\Wallet;
use App\Service\WalletService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/wallets')]
final class WalletController extends AbstractController
{
    public function __construct(
        private readonly WalletService $wallets
    ) {
    }

    #[Route('', methods: ['POST'])]
    public function create(#[MapRequestPayload] WalletRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $wallet = $this->wallets->createWallet($user, strtoupper($request->currency));

        return $this->json([
            'id' => $wallet->getId(),
            'currency' => $wallet->getCurrency(),
            'balance' => $wallet->getBalance(),
        ], 201);
    }

    #[Route('/{id}', methods: ['GET'])]
    public function show(Wallet $wallet): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$wallet->getUser()->getId()->equals($user->getId())) {
            throw $this->createAccessDeniedException();
        }

        return $this->json([
            'id' => $wallet->getId(),
            'currency' => $wallet->getCurrency(),
            'balance' => $wallet->getBalance(),
        ]);
    }
}
