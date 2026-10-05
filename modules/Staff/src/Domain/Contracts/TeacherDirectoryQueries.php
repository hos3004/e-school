<?php

declare(strict_types=1);

namespace Modules\Staff\Domain\Contracts;

use Modules\Staff\Domain\ValueObjects\TeacherDirectoryData;

/**
 * دليل المعلمين التشغيلي — قراءة مجمّعة (Batch) تمنع N+1 في القوائم.
 * «المعلم» هنا بحسب التعريف القانوني للنظام: ملف موظف نشط داخل المؤسسة
 * مرتبط بحساب فعّال؛ لا جدول teachers ولا نموذج مكرّر.
 */
interface TeacherDirectoryQueries
{
    /**
     * بيانات الدليل لمجموعة ملفات معلمين دفعة واحدة (صفحة واحدة من الجدول).
     *
     * @param list<string> $staffProfileIds
     * @return array<string, TeacherDirectoryData> مفتوحة بمعرّف ملف الموظف
     */
    public function directoryFor(string $organizationId, array $staffProfileIds): array;

    /**
     * ملفات المعلمين الحائزين على توافر معتمد وساري اليوم.
     *
     * @param list<string> $staffProfileIds
     * @return list<string>
     */
    public function withActiveAvailability(array $staffProfileIds): array;
}
