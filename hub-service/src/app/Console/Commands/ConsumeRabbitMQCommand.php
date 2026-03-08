<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Infrastructure\Messaging\RabbitMQConsumer;
use Illuminate\Console\Command;

class ConsumeRabbitMQCommand extends Command
{
    protected $signature = 'rabbitmq:consume {--prefetch=1 : Prefetch count}';
    protected $description = 'Start consuming events from RabbitMQ';

    public function handle(RabbitMQConsumer $consumer): int
    {
        $this->info('Starting RabbitMQ consumer...');
        $consumer->consume((int) $this->option('prefetch'));
        return Command::SUCCESS;
    }
}
