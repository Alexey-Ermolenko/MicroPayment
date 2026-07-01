<?php

namespace App\Messenger\Kafka;

use RdKafka\Conf;
use RdKafka\KafkaConsumer;
use RdKafka\Producer;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

final class KafkaTransport implements TransportInterface
{
    private ?Producer $producer = null;
    private ?KafkaConsumer $consumer = null;

    public function __construct(
        private readonly SerializerInterface $serializer,
        private readonly string $brokers,
        private readonly string $topic,
        private readonly string $consumerGroup,
        private readonly string $offsetReset,
    ) {
    }

    public function get(): iterable
    {
        $message = $this->consumer()->consume(1000);

        return match ($message->err) {
            RD_KAFKA_RESP_ERR_NO_ERROR => [$this->decode($message->payload)],
            RD_KAFKA_RESP_ERR__PARTITION_EOF,
            RD_KAFKA_RESP_ERR__TIMED_OUT,
            RD_KAFKA_RESP_ERR__TRANSPORT,
            RD_KAFKA_RESP_ERR_UNKNOWN_TOPIC_OR_PART => [],
            default => throw new \RuntimeException($message->errstr()),
        };
    }

    public function ack(Envelope $envelope): void
    {
        // Offsets are auto-committed by the consumer configuration.
    }

    public function reject(Envelope $envelope): void
    {
        // Offsets are auto-committed; rejected messages are not redelivered.
    }

    public function send(Envelope $envelope): Envelope
    {
        $encoded = $this->serializer->encode($envelope);
        $payload = json_encode([
            'body' => $encoded['body'],
            'headers' => $encoded['headers'] ?? [],
        ], JSON_THROW_ON_ERROR);

        $topic = $this->producer()->newTopic($this->topic);
        $topic->produce(RD_KAFKA_PARTITION_UA, 0, $payload);
        $this->producer()->poll(0);

        for ($i = 0; $i < 10 && $this->producer()->getOutQLen() > 0; ++$i) {
            $this->producer()->flush(200);
        }

        return $envelope;
    }

    private function decode(string $payload): Envelope
    {
        $data = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);

        if (!isset($data['body'])) {
            throw new MessageDecodingFailedException('Invalid Kafka message payload.');
        }

        return $this->serializer->decode([
            'body' => $data['body'],
            'headers' => $data['headers'] ?? [],
        ]);
    }

    private function producer(): Producer
    {
        if (null === $this->producer) {
            $conf = new Conf();
            $conf->set('metadata.broker.list', $this->brokers);
            $this->producer = new Producer($conf);
        }

        return $this->producer;
    }

    private function consumer(): KafkaConsumer
    {
        if (null === $this->consumer) {
            $conf = new Conf();
            $conf->set('metadata.broker.list', $this->brokers);
            $conf->set('group.id', $this->consumerGroup);
            $conf->set('auto.offset.reset', $this->offsetReset);
            $conf->set('enable.auto.commit', 'true');
            $conf->set('enable.partition.eof', 'true');
            $this->consumer = new KafkaConsumer($conf);
            $this->consumer->subscribe([$this->topic]);
        }

        return $this->consumer;
    }
}
