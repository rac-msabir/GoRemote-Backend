<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => [
    'http://localhost:5173',
    'http://127.0.0.1:5173',
    'https://go-remote-frontend-4hftwjvg3-bilals-projects-5354ef85.vercel.app',
],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];
