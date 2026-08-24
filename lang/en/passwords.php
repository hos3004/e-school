<?php

declare(strict_types=1);

/*
 * رسائل وسيط كلمات المرور. صياغة 'user' متعمَّدًا مطابقة لصياغة
 * 'sent' — لا نكشف وجود الحساب أبدًا (منع تعداد الحسابات)
 * وفق docs/15-security-model.md.
 */

return [
    'sent' => 'If an account exists for this email, the reset link will arrive shortly.',
    // Deliberately identical to the success wording — never reveal existence.
    'user' => 'If an account exists for this email, the reset link will arrive shortly.',
    'reset' => 'Your password has been reset. You can sign in now.',
    'throttled' => 'Please wait before retrying.',
    'token' => 'This password reset link is invalid or has expired.',
];
