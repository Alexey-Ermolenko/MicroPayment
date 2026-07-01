<?php

namespace App\Messenger\Kafka;

use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

final class KafkaTransportFactory implements TransportFactoryInterface
{
    public function createTransport(string $dsn, array $options, SerializerInterface $serializer): TransportInterface
    {
        $parsed = parse_url($dsn);
        parse_str($parsed['query'] ?? '', $query);

        $brokers = sprintf('%s:%d', $parsed['host'] ?? 'localhost', $parsed['port'] ?? 9092);

        return new KafkaTransport(
            $serializer,
            $brokers,
            $query['topic'] ?? 'messages',
            $query['consumer_group'] ?? 'symfony',
            $query['offset_reset'] ?? 'earliest',
        );
    }

    public function supports(string $dsn, array $options): bool
    {
        return str_starts_with($dsn, 'kafka://');
    }
}
