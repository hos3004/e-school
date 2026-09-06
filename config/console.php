<?php

declare(strict_types=1);

return [
    'enabled' => env('CONSOLE_ENABLED', false),
    'primary' => env('CONSOLE_PRIMARY', false),
    'directory_per_page' => 20,
    'directory_search_limit' => 500,
    'report_per_page' => 25,
];
