<?php

namespace App\MessageHandler;

use App\Entity\Log;
use App\Message\AbstractTransactionEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class LogHandler
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    public function __invoke(AbstractTransactionEvent $event): void
    {
        $this->em->persist(
            new Log(
                actor: 'system',
                action: $event->name(),
                entityType: 'Transaction',
                entityId: $event->transactionId,
                payload: $event->toArray(),
            ),
        );
        $this->em->flush();
    }
}
