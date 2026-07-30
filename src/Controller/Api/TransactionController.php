<?php

namespace App\Controller\Api;

use App\Dto\DepositRequest;
use App\Dto\TransferRequest;
use App\Dto\WithdrawRequest;
use App\Entity\Transaction;
use App\Entity\User;
use App\Entity\Wallet;
use App\Enum\TransactionStatus;
use App\Enum\TransactionType;
use App\Exception\InvalidTransactionException;
use App\Exception\TransactionNotRefundableException;
use App\Message\ApproveTransaction;
use App\Message\BlockTransaction;
use App\Message\CreateTransaction;
use App\Repository\TransactionRepository;
use App\Repository\WalletRepository;
use App\Service\IdempotencyService;
use RedisException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;
use Throwable;

#[Route('/api/v1/transactions')]
final class TransactionController extends AbstractController
{
    /** Keeps the user-scoped key within the idempotency_key column (uuid + separator + key). */
    private const int IDEMPOTENCY_KEY_MAX_LENGTH = 64;

    public function __construct(
        private readonly MessageBusInterface $commandBus,
        private readonly WalletRepository $wallets,
        private readonly TransactionRepository $transactions,
        private readonly IdempotencyService $idempotency,
    ) {
    }

    /**
     * @throws RedisException
     * @throws ExceptionInterface
     * @throws Throwable
     */
    #[Route('/deposit', methods: ['POST'])]
    public function deposit(#[MapRequestPayload] DepositRequest $request, Request $http): JsonResponse
    {
        $wallet = $this->ownedWallet($request->walletId);

        return $this->create(TransactionType::DEPOSIT, $request->amount, $wallet->getCurrency(), null, $wallet, $this->idempotencyKey($http));
    }

    /**
     * @throws RedisException
     * @throws ExceptionInterface
     * @throws Throwable
     */
    #[Route('/withdraw', methods: ['POST'])]
    public function withdraw(#[MapRequestPayload] WithdrawRequest $request, Request $http): JsonResponse
    {
        $wallet = $this->ownedWallet($request->walletId);

        return $this->create(TransactionType::WITHDRAWAL, $request->amount, $wallet->getCurrency(), $wallet, null, $this->idempotencyKey($http));
    }

    /**
     * @throws ExceptionInterface
     * @throws RedisException
     * @throws Throwable
     */
    #[Route('/transfer', methods: ['POST'])]
    public function transfer(#[MapRequestPayload] TransferRequest $request, Request $http): JsonResponse
    {
        $sender = $this->ownedWallet($request->senderWalletId);
        $recipient = $this->wallets->find($request->recipientWalletId)
            ?? throw $this->createNotFoundException('Recipient wallet not found.');

        if ($sender->getId()->equals($recipient->getId())) {
            throw new InvalidTransactionException('Sender and recipient wallets must differ.');
        }
        if ($sender->getCurrency() !== $recipient->getCurrency()) {
            throw new InvalidTransactionException('Currency mismatch between wallets.');
        }

        return $this->create(TransactionType::TRANSFER, $request->amount, $sender->getCurrency(), $sender, $recipient, $this->idempotencyKey($http));
    }

    /**
     * @throws ExceptionInterface
     */
    #[Route('/{id}/refund', methods: ['POST'])]
    public function refund(Transaction $transaction): JsonResponse
    {
        $this->denyUnlessParticipant($transaction);

        if (TransactionStatus::APPROVED !== $transaction->getStatus()) {
            throw new TransactionNotRefundableException('Only approved transactions can be refunded.');
        }
        if (TransactionType::REFUND === $transaction->getType()) {
            throw new TransactionNotRefundableException('A refund cannot be refunded.');
        }
        if (null !== $this->transactions->findOneRefundOf($transaction)) {
            throw new TransactionNotRefundableException('Transaction has already been refunded.');
        }

        $id = Uuid::v4();
        $this->commandBus->dispatch(
            new CreateTransaction(
                transactionId: (string) $id,
                type: TransactionType::REFUND->value,
                amount: $transaction->getAmount(),
                currency: $transaction->getCurrency(),
                refundedTransactionId: (string) $transaction->getId(),
            ),
        );

        return $this->accepted($id);
    }

    /**
     * @throws ExceptionInterface
     */
    #[Route('/{id}/approve', methods: ['POST'])]
    public function approve(Transaction $transaction): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $this->commandBus->dispatch(new ApproveTransaction((string) $transaction->getId(), (string) $this->currentUser()->getId()));

        return $this->accepted($transaction->getId());
    }

    /**
     * @throws ExceptionInterface
     */
    #[Route('/{id}/block', methods: ['POST'])]
    public function block(Transaction $transaction): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $this->commandBus->dispatch(new BlockTransaction((string) $transaction->getId(), (string) $this->currentUser()->getId()));

        return $this->accepted($transaction->getId());
    }

    #[Route('/{id}', methods: ['GET'])]
    public function show(Transaction $transaction): JsonResponse
    {
        if (!$this->isGranted('ROLE_ADMIN')) {
            $this->denyUnlessParticipant($transaction);
        }

        return $this->json([
            'id' => $transaction->getId(),
            'type' => $transaction->getType()->value,
            'status' => $transaction->getStatus()->value,
            'amount' => $transaction->getAmount(),
            'currency' => $transaction->getCurrency(),
            'senderWalletId' => $transaction->getSenderWallet()?->getId() ?: null,
            'recipientWalletId' => $transaction->getRecipientWallet()?->getId() ?: null,
            'refundedTransactionId' => $transaction->getRefundedTransaction()?->getId() ?: null,
            'createdAt' => $transaction->getCreatedAt()->format(DATE_ATOM),
        ]);
    }

    /**
     * @throws ExceptionInterface
     * @throws RedisException
     * @throws Throwable
     */
    private function create(
        TransactionType $type,
        int $amount,
        string $currency,
        ?Wallet $sender,
        ?Wallet $recipient,
        string $idempotencyKey
    ): JsonResponse {
        // Covers a repeat that arrives after the Redis claim expired but the transaction still exists.
        $existing = $this->transactions->findOneByIdempotencyKey($idempotencyKey);
        if (null !== $existing) {
            return $this->accepted($existing->getId());
        }

        $id = Uuid::v4();
        $reservedId = $this->idempotency->reserve($idempotencyKey, (string) $id);
        if ($reservedId !== (string) $id) {
            // A parallel request with the same key won the claim: answer with its transaction id.
            return $this->accepted(Uuid::fromString($reservedId));
        }

        try {
            $this->commandBus->dispatch(
                new CreateTransaction(
                    transactionId: (string) $id,
                    type: $type->value,
                    amount: $amount,
                    currency: $currency,
                    senderWalletId: null !== $sender ? (string) $sender->getId() : null,
                    recipientWalletId: null !== $recipient ? (string) $recipient->getId() : null,
                    idempotencyKey: $idempotencyKey,
                ),
            );
        } catch (Throwable $e) {
            // The command never reached Kafka: free the key instead of pointing it at a phantom id.
            $this->idempotency->release($idempotencyKey);

            throw $e;
        }

        return $this->accepted($id);
    }

    private function accepted(Uuid $id): JsonResponse
    {
        return $this->json(['id' => (string) $id, 'status' => TransactionStatus::PENDING->value], 202);
    }

    private function ownedWallet(string $id): Wallet
    {
        $wallet = $this->wallets->find($id) ?? throw $this->createNotFoundException('Wallet not found.');
        if (!$wallet->getUser()->getId()->equals($this->currentUser()->getId())) {
            throw $this->createAccessDeniedException();
        }

        return $wallet;
    }

    private function denyUnlessParticipant(Transaction $transaction): void
    {
        $userId = $this->currentUser()->getId();
        foreach ([$transaction->getSenderWallet(), $transaction->getRecipientWallet()] as $wallet) {
            if (null !== $wallet && $wallet->getUser()->getId()->equals($userId)) {
                return;
            }
        }

        throw $this->createAccessDeniedException();
    }

    private function currentUser(): User
    {
        /** @var User $user */
        $user = $this->getUser();

        return $user;
    }

    /**
     * Returns the key scoped to the current user: the same header value from two users must not collide,
     * neither in Redis nor in the unique idempotency_key index.
     */
    private function idempotencyKey(Request $request): string
    {
        $key = trim((string) $request->headers->get('Idempotency-Key'));
        if ('' === $key) {
            throw new BadRequestHttpException('Idempotency-Key header is required.');
        }
        if (mb_strlen($key) > self::IDEMPOTENCY_KEY_MAX_LENGTH) {
            throw new BadRequestHttpException(sprintf('Idempotency-Key must not exceed %d characters.', self::IDEMPOTENCY_KEY_MAX_LENGTH));
        }

        return sprintf('%s:%s', $this->currentUser()->getId(), $key);
    }
}
