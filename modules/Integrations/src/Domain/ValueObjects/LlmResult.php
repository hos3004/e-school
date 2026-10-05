<?php

declare(strict_types=1);

namespace Modules\Integrations\Domain\ValueObjects;

/**
 * نتيجة نداء النموذج اللغوي.
 *
 * تتبع نفس عقد GatewayResult: البوابة لا ترمي استثناءً أبدًا، والمستهلك يتفرّع
 * على isAccepted()/isRetryable() فقط. السبب أن فشل المزوّد هنا يجب أن ينتهي
 * برسالة مهذّبة وتحويل إلى الواتساب الرسمي، لا بصفحة خطأ في وجه طالب.
 *
 * عدّادات التوكِن تُرجَع حتى مع الرفض متى أعادها المزوّد، لأن المحاولة الفاشلة
 * بعد توليد جزئي تكون قد كلّفت فعلًا، وحساب الاستهلاك يجب ألا يفوّتها.
 */
final readonly class LlmResult
{
    private function __construct(
        private bool $accepted,
        private bool $retryable,
        private string $text,
        private ?string $error,
        public string $model,
        public int $inputTokens,
        public int $outputTokens,
        public int $latencyMilliseconds,
        public ?string $stopReason,
    ) {}

    public static function accepted(
        string $text,
        string $model,
        int $inputTokens,
        int $outputTokens,
        int $latencyMilliseconds,
        ?string $stopReason = null,
    ): self {
        return new self(
            true,
            false,
            $text,
            null,
            $model,
            $inputTokens,
            $outputTokens,
            $latencyMilliseconds,
            $stopReason,
        );
    }

    public static function rejected(
        string $error,
        bool $retryable,
        string $model = '',
        int $inputTokens = 0,
        int $outputTokens = 0,
        int $latencyMilliseconds = 0,
    ): self {
        return new self(
            false,
            $retryable,
            '',
            $error,
            $model,
            $inputTokens,
            $outputTokens,
            $latencyMilliseconds,
            null,
        );
    }

    public function isAccepted(): bool
    {
        return $this->accepted;
    }

    public function isRetryable(): bool
    {
        return !$this->accepted && $this->retryable;
    }

    /** النص المولَّد — فارغ دائمًا عند الرفض. */
    public function text(): string
    {
        return $this->text;
    }

    public function error(): ?string
    {
        return $this->error;
    }

    /**
     * هل بُتر التوليد لبلوغ سقف التوكِن؟
     *
     * يهم لأن ردًّا مبتورًا في منتصف جملة يصل للمستخدم كنص ناقص؛ المستهلك
     * يفضّل عندها الرد الجاهز على نشر نصف إجابة.
     */
    public function wasTruncated(): bool
    {
        return $this->stopReason === 'max_tokens';
    }
}
