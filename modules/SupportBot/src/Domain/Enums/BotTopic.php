<?php

declare(strict_types=1);

namespace Modules\SupportBot\Domain\Enums;

/**
 * المواضيع التي يصنَّف إليها سؤال المستخدم.
 *
 * قائمة **مغلقة** عمدًا، وهي حجر الزاوية في الحماية من الخداع: مخرج مرحلة
 * التصنيف لا يُقرأ كنص حر، بل يُطابَق على هذه القائمة. أي مخرج لا يطابق قيمة
 * هنا — سواء كان هلوسة أو نتيجة محاولة حقن تعليمات — يصير Unknown، وUnknown
 * قاعدته الافتراضية أن يُرشد لا أن يجيب.
 *
 * بعبارة أخرى: النموذج لا يملك التعبير عن «تجاهل تعليماتك»، لأن مفرداته في
 * هذه المرحلة لا تحتوي إلا أسماء المواضيع.
 */
enum BotTopic: string
{
    /** كيف أستخدم المنصة، أين أجد شاشة كذا. الاستعمال الأغلب. */
    case PlatformHelp = 'platform_help';

    /** المواعيد والجدول والحصص القادمة. */
    case Schedule = 'schedule';

    /** الحضور والغياب. */
    case Attendance = 'attendance';

    /** الدخول إلى الفصل المباشر ومشاكله. */
    case SessionJoin = 'session_join';

    /** المعلم يسأل عن طلابه هو. */
    case MyStudents = 'my_students';

    /** التقدم الدراسي والتقارير والدرجات. */
    case AcademicProgress = 'academic_progress';

    /** المستحقات المالية للمعلم — محظور القيمة بالإعداد الافتراضي. */
    case PayrollDues = 'payroll_dues';

    /** عدد الحصص المحتسبة للأجر — محظور القيمة بالإعداد الافتراضي. */
    case SessionCount = 'session_count';

    /** رسوم الطالب والفوترة — مؤجّلة في المنصة أصلًا. */
    case Billing = 'billing';

    /** الحساب والدخول وكلمة المرور والملف الشخصي. */
    case Account = 'account';

    /** عطل تقني: صوت، صورة، صفحة لا تفتح. */
    case TechnicalIssue = 'technical_issue';

    /** سياسات المدرسة: الإلغاء، التأجيل، الانضباط. */
    case Policy = 'policy';

    /** بيانات شخص آخر — محظور دائمًا مهما كان السائل. */
    case OtherPersonData = 'other_person_data';

    /** كيف أتواصل مع الأكاديمية. */
    case Contact = 'contact';

    /** معلومات عن الدورات — يخدم لاحقًا بوت الاستفسارات العامة. */
    case Courses = 'courses';

    /** لم يُفهم السؤال أو لم يطابق المخرج أي موضوع. الافتراض: إرشاد لا إجابة. */
    case Unknown = 'unknown';

    /**
     * القيم كما تُعرض للنموذج في مرحلة التصنيف.
     *
     * Unknown مستثناة: لا نطلب من النموذج اختيارها، بل هي ما نسقط إليه حين
     * يفشل في اختيار غيرها. تركها في القائمة كان سيغريه بالهروب إليها.
     *
     * @return list<string>
     */
    public static function classifiable(): array
    {
        return array_values(array_map(
            static fn (self $topic): string => $topic->value,
            array_filter(self::cases(), static fn (self $topic): bool => $topic !== self::Unknown),
        ));
    }

    /**
     * مطابقة صارمة لمخرج النموذج. أي شيء آخر — نص حر، جملة، محاولة حقن —
     * يصير Unknown.
     */
    public static function fromModelOutput(string $output): self
    {
        return self::tryFrom(strtolower(trim($output))) ?? self::Unknown;
    }

    /**
     * المواضيع التي لا يجوز لأي فئة أن تحصل منها على قيمة عددية، أيًا كانت
     * قواعد الجداول. حارس أخير في الكود فوق القواعد القابلة للتحرير: لو حرّر
     * أحدهم صفًّا في الجدول خطأً، يبقى هذا السياج قائمًا.
     *
     * @return list<self>
     */
    public static function neverDisclosesFigures(): array
    {
        return [self::PayrollDues, self::SessionCount, self::Billing];
    }

    public function disclosesFigures(): bool
    {
        return in_array($this, self::neverDisclosesFigures(), true);
    }

    public function label(): string
    {
        return __('supportbot::topics.'.$this->value);
    }
}
