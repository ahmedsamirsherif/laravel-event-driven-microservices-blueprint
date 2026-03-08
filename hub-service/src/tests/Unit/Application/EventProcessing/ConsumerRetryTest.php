<?php

declare(strict_types=1);

use App\Infrastructure\Messaging\RabbitMQConsumer;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

it('reads retry count from message headers and defaults to 0', function () {
    $consumer = app(RabbitMQConsumer::class);

    $noHeaders = new AMQPMessage('{}');
    expect($consumer->getRetryCount($noHeaders))->toBe(0);

    $withHeaders = new AMQPMessage('{}', [
        'application_headers' => new AMQPTable(['x-retry-count' => 2]),
    ]);
    expect($consumer->getRetryCount($withHeaders))->toBe(2);
});

it('has MAX_RETRIES=3 and resolves from container', function () {
    $ref = new ReflectionClass(RabbitMQConsumer::class);
    expect($ref->getConstants()['MAX_RETRIES'] ?? null)->toBe(3);
    expect(app(RabbitMQConsumer::class))->toBeInstanceOf(RabbitMQConsumer::class);
});
