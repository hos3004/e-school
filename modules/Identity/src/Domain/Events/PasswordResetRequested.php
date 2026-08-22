<?php

declare(strict_types=1);

namespace Modules\Identity\Domain\Events;

use Shared\Domain\DomainEvent;

/**
 * طُلب رابط إعادة تعيين كلمة المرور لبريد معيّن.
 *
 * الرمز الخام يمرّ هنا فقط إلى مستمع الإشعارات الموثوق ليُرسل بالبريد،
 * كما في تدفق Laravel القياسي؛ المخزَّن في قاعدة البيانات مجرّد Hash.
 * ممنوع على أي مستمع تسجيل هذا الحدث أو حمولته في سجلات دائمة.
 */
final class PasswordResetRequested extends DomainEvent
{
    public function __construct(
        public readonly string $email,
        public readonly string $userId,
        public readonly string $token,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($actorId, $correlationId);
    }

    public function name(): string
    {
        return 'identity.password_reset_requested';
    }

    public function module(): string
    {
        return 'Identity';
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'email' => $this->email,
            'user_id' => $this->userId,
        ];
    }
}
