<?php

return [
    // A conversation idle for longer than this starts over from the start step.
    'session_ttl_minutes' => (int) env('CHATBOT_SESSION_TTL', 30),

    // Shared secret for HMAC-signed channel webhooks (X-Signature header).
    'webhook_secret' => env('CHATBOT_WEBHOOK_SECRET', 'local-dev-secret'),

    'queue' => env('CHATBOT_QUEUE', 'chatbot'),
];
