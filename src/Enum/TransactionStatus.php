<?php

namespace App\Enum;

enum TransactionStatus: string
{
    case PENDING = 'PENDING';
    case APPROVED = 'APPROVED';
    case BLOCKED = 'BLOCKED';
    case FAILED = 'FAILED';
}
