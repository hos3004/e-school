<?php

declare(strict_types=1);

namespace Modules\Notifications\Application\Services;

/**
 * يوقف إشعار طرف بعينه (طالب/معلم) عن عملية إدارية واحدة، دون مسّ إعداد
 * الفئة العام ولا تعطيل القناة كلها.
 *
 * Singleton بعمر الطلب الواحد: المتحكّم يفعّله قبل استدعاء الإجراء مباشرة،
 * والمستمع المتزامن الذي يُطلَق أثناء نفس الطلب يقرأه فورًا. لا يعبر حدود
 * الطلب — عامل قائمة الانتظار عملية منفصلة لا يرى هذه الحالة أبدًا، وهذا
 * مقصود: الكتم قرار لحظي لهذا التعديل وحده، لا يصح أن يُخزَّن أو يتسرّب
 * لطلب لاحق.
 */
final class NotificationRecipientSilencer
{
    /** @var list<string> */
    private array $roles = [];

    /** يوقف كل مستلم يتبع هذا الدور — 'student' يشمل ولي الأمر أيضًا. */
    public function silence(string $role): void
    {
        $this->roles[] = $role;
    }

    /** @return list<string> */
    public function silencedRoles(): array
    {
        return array_values(array_unique($this->roles));
    }

    public function isAudienceSilenced(string $audience): bool
    {
        return match ($audience) {
            'student', 'guardian' => in_array('student', $this->roles, true),
            'teacher' => in_array('teacher', $this->roles, true),
            default => false,
        };
    }

    /**
     * حقل الحمولة الحرفي (student_user_ids، teacher_user_id...) يحمل معرّفات
     * جاهزة تتجاوز اشتقاق الدور، فيُفحص اسمه لا الدور المُعلَن للحدث.
     */
    public function isRecipientFieldSilenced(string $field): bool
    {
        $field = mb_strtolower($field);

        if (in_array('student', $this->roles, true)
            && (str_contains($field, 'student') || str_contains($field, 'guardian'))) {
            return true;
        }

        if (in_array('teacher', $this->roles, true) && str_contains($field, 'teacher')) {
            return true;
        }

        return false;
    }
}
