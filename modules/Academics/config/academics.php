<?php

declare(strict_types=1);

return [
    'age' => ['minimum' => 3, 'maximum' => 99],
    'session_minutes' => ['default' => 60, 'minimum' => 15, 'course_minimum' => 10, 'maximum' => 480],
    'sessions_per_week' => ['minimum' => 1, 'maximum' => 14],
    'reason' => ['minimum_length' => 3, 'maximum_length' => 1000],
];
