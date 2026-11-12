<?php

return [
    'paths' => ['api/*'],
    'allowed_methods' => ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    // Para otra dirección privada, declararla explícitamente en el entorno.
    'allowed_origins' => array_values(array_filter(array_map('trim', explode(',', env('CORS_ALLOWED_ORIGINS', ''))))),
    'allowed_origins_patterns' => ['#^https?://(localhost|127\.0\.0\.1)(:[0-9]+)?$#'],
    'allowed_headers' => ['Accept', 'Content-Type', 'X-Requested-With'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false,
];
