<?php

declare(strict_types=1);

namespace Modules\SupportBot\Domain\Enums;

/**
 * الفئة التي يخاطبها البوت.
 *
 * **الفئة تحدد النبرة والمعرفة المعروضة وقواعد الحدود — ولا تحدد الصلاحية.**
 * هذا التمييز هو عمود التصميم كله: من يرى ماذا يقرره `$user->can()` وسياسات
 * المنصة نفسها، تمامًا كما لو فتح الصفحة بيده. الفئة هنا لا تفتح بابًا ولا
 * تغلقه؛ هي تجيب عن سؤال «بأي لغة أخاطبه وأي أمثلة تنفعه».
 *
 * ولذلك فالتجميع الخشن هنا آمن: مدقّق ومشرف جودة يشتركان في نبرة واحدة بينما
 * يختلف ما يصل كلًّا منهما من بيانات اختلافًا كاملًا عبر الصلاحيات.
 *
 * ملاحظة: لا يجوز لأي كود أن يفحص اسم دور لاتخاذ قرار وصول — هذه الخريطة
 * استثناء معلن ومحصور في مكان واحد، على غرار AccessControlPopupAudienceResolver.
 */
enum BotAudience: string
{
    case Student = 'student';
    case Guardian = 'guardian';
    case Teacher = 'teacher';
    case Supervisor = 'supervisor';
    case Administrator = 'administrator';

    /**
     * خريطة الدور إلى الفئة. كل أدوار النظام العشرة مذكورة عمدًا: الخريطة
     * الناقصة في موضع آخر من المشروع تترك أربعة أدوار بلا فئة، فيسقط أصحابها
     * إلى «بلا شخصية» — وهو ما لا نريد تكراره هنا.
     *
     * @return array<string, self>
     */
    public static function roleMap(): array
    {
        return [
            'platform_admin' => self::Administrator,
            'academic_supervisor' => self::Supervisor,
            'finance_supervisor' => self::Supervisor,
            'registrar' => self::Supervisor,
            'communications_officer' => self::Supervisor,
            'auditor' => self::Supervisor,
            'supervisor' => self::Supervisor,
            'teacher' => self::Teacher,
            'guardian' => self::Guardian,
            'student' => self::Student,
        ];
    }

    /**
     * ترتيب الأسبقية حين يحمل الشخص أكثر من دور.
     *
     * يحدث فعلًا: معلم هو وليّ أمر طالب في نفس الأكاديمية. نختار الأوسع نبرةً
     * وأمثلةً، والأمان غير متأثر لأن البيانات تمر على الصلاحيات لا على الفئة.
     *
     * @return list<self>
     */
    public static function precedence(): array
    {
        return [
            self::Administrator,
            self::Supervisor,
            self::Teacher,
            self::Guardian,
            self::Student,
        ];
    }

    /**
     * الفئة المستخلصة من قائمة أدوار. تعيد null إذا لم يطابق أي دور معروف —
     * والمستدعي يرفض عندها بدل أن يخمّن فئة.
     *
     * @param list<string> $roleNames
     */
    public static function fromRoleNames(array $roleNames): ?self
    {
        $map = self::roleMap();
        $matched = [];

        foreach ($roleNames as $roleName) {
            $audience = $map[$roleName] ?? null;

            if ($audience instanceof self) {
                $matched[$audience->value] = $audience;
            }
        }

        if ($matched === []) {
            return null;
        }

        foreach (self::precedence() as $candidate) {
            if (isset($matched[$candidate->value])) {
                return $candidate;
            }
        }

        return null;
    }

    public function label(): string
    {
        return __('supportbot::audiences.'.$this->value);
    }
}
