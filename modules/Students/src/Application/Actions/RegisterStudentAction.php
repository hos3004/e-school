<?php

declare(strict_types=1);

namespace Modules\Students\Application\Actions;

use Modules\Students\Domain\Models\StudentProfile;
use Shared\Support\BusinessRuleViolation;

/**
 * @deprecated ملفات الطلاب لا تُنشأ إلا عبر AcceptRegistrationApplicationAction.
 *
 * أُبقي الاسم مؤقتًا كي يفشل أي مستهلك قديم برسالة مجال واضحة بدل أن يتجاوز
 * دورة القبول أو يسقط بخطأ ربط تقني غير مفهوم.
 */
final readonly class RegisterStudentAction
{
    /**
     * @param array<string, mixed> $data بيانات قديمة لا تُستخدم عمدًا
     */
    public function execute(array $data): StudentProfile
    {
        throw BusinessRuleViolation::make(
            'students.direct_profile_creation_disabled',
            'students::errors.direct_profile_creation_disabled',
        );
    }
}
