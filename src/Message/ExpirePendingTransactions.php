<?php

namespace App\Message;

/**
 * Scheduler tick. Deliberately not a Command: it must stay on the scheduler
 * transport instead of being routed to Kafka.
 */
final class ExpirePendingTransactions
{
}
