<?php

declare(strict_types=1);

namespace Modules\Notifications\Domain\Enums;

/**
 * أنواع الجمهور التي يدعمها الإرسال الإداري اليدوي.
 *
 * المجموعة ليست وحدة التجميع الوحيدة: المدرسة تعمل اليوم بجداول فردية،
 * فالكورس والجدول هما «الفصل» الفعلي الذي تُراسل أطرافه.
 */
enum ManualRecipientType: string
{
    case Student = 'student';

    case Teacher = 'teacher';

    case Guardian = 'guardian';

    case Group = 'group';

    case Course = 'course';

    case Schedule = 'schedule';

    /** قائمة أشخاص يختارهم المرسِل بالاسم — targetId قائمة user IDs مفصولة بفواصل. */
    case People = 'people';

    /** كل طلاب المؤسسة النشطين. */
    case AllStudents = 'students_all';

    /** كل معلمي المؤسسة النشطين. */
    case AllTeachers = 'teachers_all';

    /** كل أولياء أمور المؤسسة غير المؤرشفين. */
    case AllGuardians = 'guardians_all';

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(static fn (self $type): array => [$type->value => $type->label()])
            ->all();
    }

    /**
     * هل يحتمل هذا الهدف أكثر من طرف فيصح تقييده بجمهور؟
     */
    public function isAudienceScoped(): bool
    {
        return in_array($this, [self::Group, self::Course, self::Schedule], true);
    }

    /**
     * هل يحمل targetId معرّفًا حقيقيًا يُختار من قائمة؟
     *
     * «كل الطلاب» و«كل المعلمين» جمهورهما هو المؤسسة نفسها، فلا هدف يُختار،
     * و«قائمة أشخاص» هدفها القائمة ذاتها لا عنصرًا واحدًا منها.
     */
    public function needsSingleTarget(): bool
    {
        return !in_array($this, [self::People, self::AllStudents, self::AllTeachers, self::AllGuardians], true);
    }

    /** هل يختار المرسِل أشخاصًا بأسمائهم لهذا النوع؟ */
    public function picksPeople(): bool
    {
        return in_array($this, [self::Student, self::Teacher, self::Guardian, self::People], true);
    }

    public function label(): string
    {
        return __('notifications::fields.recipient_types.'.$this->value);
    }
}
