<?php

namespace App\MessageHandler;

use App\Entity\Notification;
use App\Enum\TransactionType;
use App\Message\AbstractTransactionEvent;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class NotificationHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserRepository $users,
    ) {
    }

    public function __invoke(AbstractTransactionEvent $event): void
    {
        $refund = TransactionType::REFUND->value === $event->type;
        $message = match ($event->name()) {
            'TransactionApproved' => $refund
                ? sprintf('Transaction of %d %s was refunded.', $event->amount, $event->currency)
                : sprintf('Transaction of %d %s approved.', $event->amount, $event->currency),
            'TransactionBlocked' => sprintf('Transaction of %d %s was blocked.', $event->amount, $event->currency),
            'TransactionFailed' => sprintf('Transaction of %d %s failed: %s', $event->amount, $event->currency, $event->reason ?? 'unknown'),
            default => null,
        };

        if (null === $message) {
            return;
        }

        foreach ([$event->senderUserId, $event->recipientUserId] as $userId) {
            if (null === $userId || null === $user = $this->users->find($userId)) {
                continue;
            }
            $this->em->persist(new Notification($user, $event->name(), $message, $event->toArray()));
        }

        $this->em->flush();
    }
}
