<?php

return [
    'mock_mode' => filter_var(env('MIKROTIK_MOCK_MODE', true), FILTER_VALIDATE_BOOLEAN),
    'default_port' => (int) env('MIKROTIK_DEFAULT_PORT', 8728),
    'timeout' => (int) env('MIKROTIK_TIMEOUT', 5),
    'attempts' => (int) env('MIKROTIK_ATTEMPTS', 1),
];