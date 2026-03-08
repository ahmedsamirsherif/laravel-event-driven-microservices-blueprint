<?php

return [
    'host'           => env('RABBITMQ_HOST', 'rabbitmq'),
    'port'           => env('RABBITMQ_PORT', 5672),
    'user'           => env('RABBITMQ_USER', 'guest'),
    'password'       => env('RABBITMQ_PASSWORD', 'guest'),
    'vhost'          => env('RABBITMQ_VHOST', '/'),
    'management_url' => env('RABBITMQ_MANAGEMENT_URL', 'http://rabbitmq:15672'),
];
