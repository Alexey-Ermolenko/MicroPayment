<?php

namespace App\Entity;

use App\Repository\LogRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: LogRepository::class)]
#[ORM\Table(name: 'logs')]
#[ORM\Index(name: 'idx_log_entity', columns: ['entity_type', 'entity_id'])]
class Log
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 128)]
    private string $actor;

    #[ORM\Column(length: 64)]
    private string $action;

    #[ORM\Column(length: 64)]
    private string $entityType;

    #[ORM\Column(length: 64)]
    private string $entityId;

    #[ORM\Column(type: 'json')]
    private array $payload;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    public function __construct(string $actor, string $action, string $entityType, string $entityId, array $payload = [])
    {
        $this->id = Uuid::v4();
        $this->actor = $actor;
        $this->action = $action;
        $this->entityType = $entityType;
        $this->entityId = $entityId;
        $this->payload = $payload;
        $this->createdAt = new DateTimeImmutable();
    }
}
