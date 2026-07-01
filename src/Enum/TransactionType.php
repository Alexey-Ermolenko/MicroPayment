<?php

namespace App\Enum;

enum TransactionType: string
{
    case DEPOSIT = 'DEPOSIT';
    case WITHDRAWAL = 'WITHDRAWAL';
    case TRANSFER = 'TRANSFER';
    case REFUND = 'REFUND';
}
