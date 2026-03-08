<?php

declare(strict_types=1);

namespace App\Infrastructure\Messaging;

use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

final class RabbitMQPublisher
{
    private const EXCHANGE = 'employee_events';

    private ?AMQPStreamConnection $connection = null;
    private ?AMQPChannel $channel = null;

    public function __destruct()
    {
        $this->disconnect();
    }

    public function publish(string $routingKey, array $payload): void
    {
        try {
            $msg = new AMQPMessage(
                body: json_encode($payload, JSON_THROW_ON_ERROR),
                properties: [
                    'content_type' => 'application/json',
                    'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                    'message_id' => $payload['event_id'] ?? uniqid('', true),
                ],
            );

            $this->channel()->basic_publish($msg, self::EXCHANGE, $routingKey);

            Log::info('[RabbitMQPublisher][publish] Event published', [
                'routing_key' => $routingKey,
                'event_id' => $payload['event_id'] ?? 'unknown',
            ]);
        } catch (\Throwable $e) {
            Log::error('[RabbitMQPublisher][publish] Failed to publish event', [
                'routing_key' => $routingKey,
                'event_id' => $payload['event_id'] ?? 'unknown',
                'exception' => $e,
            ]);

            $this->disconnect();

            throw $e;
        }
    }

    private function channel(): AMQPChannel
    {
        if ($this->channel !== null && $this->connection !== null && $this->connection->isConnected()) {
            return $this->channel;
        }

        $this->disconnect();

        $this->connection = new AMQPStreamConnection(
            host: config('rabbitmq.host'),
            port: (int) config('rabbitmq.port'),
            user: config('rabbitmq.user'),
            password: config('rabbitmq.password'),
            vhost: config('rabbitmq.vhost'),
        );

        $this->channel = $this->connection->channel();
        $this->channel->exchange_declare(
            exchange: self::EXCHANGE,
            type: 'topic',
            passive: false,
            durable: true,
            auto_delete: false,
        );

        return $this->channel;
    }

    private function disconnect(): void
    {
        try {
            $this->channel?->close();
        } catch (\Throwable) {
        } finally {
            $this->channel = null;
        }

        try {
            if ($this->connection !== null && $this->connection->isConnected()) {
                $this->connection->close();
            }
        } catch (\Throwable) {
        } finally {
            $this->connection = null;
        }
    }
}
