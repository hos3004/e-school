<?php

declare(strict_types=1);

return [
    'phone_password_reset' => [
        'otp_digits' => (int) env('PHONE_RESET_OTP_DIGITS', 6),
        'ttl_minutes' => (int) env('PHONE_RESET_OTP_TTL_MINUTES', 15),
        'resend_after_seconds' => (int) env('PHONE_RESET_RESEND_AFTER_SECONDS', 60),
        'max_verification_attempts' => (int) env('PHONE_RESET_MAX_ATTEMPTS', 5),
        'edge_requests_per_hour' => (int) env('PHONE_RESET_REQUESTS_PER_HOUR', 3),
        'edge_verifications_per_minute' => (int) env('PHONE_RESET_VERIFICATIONS_PER_MINUTE', 5),
    ],
];
