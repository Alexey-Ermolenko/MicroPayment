<?php

namespace App\Message;

interface DomainEvent
{
    public function name(): string;

    public function toArray(): array;
}
