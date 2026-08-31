<?php

declare(strict_types=1);

return [
    'managed_accounts' => [
        // هذه الأدوار لها معالجات أشخاص مستقلة تنشئ ملف المجال معها.
        'excluded_roles' => ['student', 'teacher', 'guardian'],
    ],
    'directory' => [
        'max_results' => (int) Illuminate\Support\Env::get('IDENTITY_DIRECTORY_MAX_RESULTS', 50),
    ],

    'phone_password_reset' => [
        'otp_digits' => (int) Illuminate\Support\Env::get('PHONE_RESET_OTP_DIGITS', 6),
        'ttl_minutes' => (int) Illuminate\Support\Env::get('PHONE_RESET_OTP_TTL_MINUTES', 15),
        'resend_after_seconds' => (int) Illuminate\Support\Env::get('PHONE_RESET_RESEND_AFTER_SECONDS', 60),
        'max_verification_attempts' => (int) Illuminate\Support\Env::get('PHONE_RESET_MAX_ATTEMPTS', 5),
        'edge_requests_per_hour' => (int) Illuminate\Support\Env::get('PHONE_RESET_REQUESTS_PER_HOUR', 3),
        'edge_verifications_per_minute' => (int) Illuminate\Support\Env::get('PHONE_RESET_VERIFICATIONS_PER_MINUTE', 5),
    ],
];
