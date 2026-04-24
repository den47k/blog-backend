<?php

return [
    'url' => env('CENTRIFUGO_URL', 'http://centrifugo:8000'),

    'public_url' => env('CENTRIFUGO_PUBLIC_URL', 'ws://localhost:8000/connection/websocket'),

    'api_key' => env('CENTRIFUGO_API_KEY'),

    'token_hmac_secret' => env('CENTRIFUGO_TOKEN_HMAC_SECRET'),

    'connection_token_ttl' => (int) env('CENTRIFUGO_CONNECTION_TOKEN_TTL', 3600),

    'subscription_token_ttl' => (int) env('CENTRIFUGO_SUBSCRIPTION_TOKEN_TTL', 900),

    'namespaces' => [
        'user' => 'user',
        'conversation' => 'conv',
    ],

    'http_timeout' => (int) env('CENTRIFUGO_HTTP_TIMEOUT', 3),
];
