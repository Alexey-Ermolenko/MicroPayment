<?php

namespace App\Messenger\Kafka;

use RdKafka\Message;
use Symfony\Component\Messenger\Stamp\NonSendableStampInterface;

/**
 * Carries the consumed Kafka message so its offset can be committed on ack/reject.
 */
final readonly class KafkaMessageStamp implements NonSendableStampInterface
{
    public function __construct(
        public Message $message,
    ) {
    }
}