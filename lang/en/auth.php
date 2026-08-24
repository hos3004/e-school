<?php

declare(strict_types=1);

/*
 * Unified authentication messages — the reset wording is intentionally
 * identical for success and unknown-account cases to prevent account
 * enumeration per docs/15-security-model.md.
 */

return [
    'failed' => 'These credentials do not match our records.',
    'password' => 'The provided password is incorrect.',
    'throttle' => 'Too many attempts. Try again in :seconds seconds.',
];
