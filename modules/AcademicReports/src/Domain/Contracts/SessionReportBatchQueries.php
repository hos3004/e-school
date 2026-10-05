<?php

declare(strict_types=1);

namespace Modules\AcademicReports\Domain\Contracts;

use Modules\AcademicReports\Domain\ValueObjects\SessionReportBatchData;

/**
 * قراءة مجمّعة لتقارير الحصص المرسَلة لدفعة من الحصص، دون تسريب نموذج Eloquent.
 *
 * يستهلكها موديول Reporting لبناء تجميعات عابرة للحصص (حسب البرنامج أو
 * الفترة الزمنية) دون معرفة جدول `session_reports` مباشرة. الملاحظة
 * الإشرافية الخاصة (`supervisor_private_note`) لا تدخل هذا العقد أبدًا.
 */
interface SessionReportBatchQueries
{
    /**
     * @param list<string> $sessionIds
     * @return list<SessionReportBatchData> تقارير مُرسَلة فقط (submitted_at غير فارغ)
     */
    public function forSessions(array $sessionIds): array;
}
