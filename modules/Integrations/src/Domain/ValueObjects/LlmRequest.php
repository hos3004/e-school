<?php

declare(strict_types=1);

namespace Modules\Integrations\Domain\ValueObjects;

/**
 * طلب واحد إلى نموذج لغوي.
 *
 * البوابة لا تعرف شيئًا عن المدرسة: لا أدوار ولا مواضيع ولا صلاحيات. من يبني
 * هذا الطلب هو من قرر — قبل الوصول إلى هنا — ما الذي يجوز أن يدخل النموذج.
 * الفصل مقصود: الحارس يعيش في موديول البوت، والبوابة تنقل فقط.
 */
final readonly class LlmRequest
{
    /** تصنيف سؤال المستخدم إلى موضوع من قائمة مغلقة. */
    public const string PURPOSE_CLASSIFY = 'classify';

    /** صياغة الرد النهائي بعد أن سمح الحارس. */
    public const string PURPOSE_ANSWER = 'answer';

    /**
     * @param list<LlmMessage> $messages
     * @param list<string>     $stopSequences
     * @param self::PURPOSE_*  $purpose غرض النداء — يفصل تكلفة التصنيف عن تكلفة
     *                                  الرد في حساب الاستهلاك، ويختار الرد
     *                                  المُعلَّب في المشغّل الوهمي.
     */
    public function __construct(
        public string $organizationId,
        public string $model,
        public string $system,
        public array $messages,
        public int $maxTokens,
        public string $purpose = self::PURPOSE_ANSWER,
        public float $temperature = 0.0,
        public array $stopSequences = [],
    ) {}

    /**
     * @return list<array{role: string, content: string}>
     */
    public function messagesPayload(): array
    {
        return array_values(array_map(
            static fn (LlmMessage $message): array => $message->toArray(),
            $this->messages,
        ));
    }

    /** آخر رسالة من المستخدم — يحتاجها المشغّل الوهمي لردّ حتمي مفهوم. */
    public function lastUserMessage(): string
    {
        for ($index = count($this->messages) - 1; $index >= 0; $index--) {
            $message = $this->messages[$index] ?? null;

            if ($message instanceof LlmMessage && $message->role === 'user') {
                return $message->content;
            }
        }

        return '';
    }
}
