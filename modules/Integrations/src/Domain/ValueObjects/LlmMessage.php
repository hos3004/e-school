<?php

declare(strict_types=1);

namespace Modules\Integrations\Domain\ValueObjects;

/**
 * دور واحد داخل محادثة تُرسَل إلى نموذج لغوي.
 *
 * بدائية عمدًا: لا Eloquent ولا كيانات موديولات أخرى، لأن العقود لا يجوز أن
 * تعرف نماذج البيانات (tests/Architecture تفرض ذلك).
 */
final readonly class LlmMessage
{
    private function __construct(
        public string $role,
        public string $content,
    ) {}

    public static function fromUser(string $content): self
    {
        return new self('user', $content);
    }

    public static function fromAssistant(string $content): self
    {
        return new self('assistant', $content);
    }

    /**
     * @return array{role: string, content: string}
     */
    public function toArray(): array
    {
        return [
            'role' => $this->role,
            'content' => $this->content,
        ];
    }
}
