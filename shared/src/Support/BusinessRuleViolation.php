<?php

declare(strict_types=1);

namespace Shared\Support;

use RuntimeException;

/**
 * خرق قاعدة عمل — وليس خطأ تقنيًا.
 *
 * يُترجم تلقائيًا إلى 422 مع رسالة مفهومة للمستخدم بلغته، ولا يُرسل إلى Sentry.
 */
class BusinessRuleViolation extends RuntimeException
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        public readonly string $rule,
        string $message,
        public readonly array $context = [],
    ) {
        parent::__construct($message);
    }

    /**
     * @param array<string, mixed> $replace
     */
    public static function make(string $rule, string $translationKey, array $replace = []): self
    {
        return new self($rule, __($translationKey, $replace), $replace);
    }
}
