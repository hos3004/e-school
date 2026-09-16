<?php

declare(strict_types=1);

namespace Modules\Reporting\Domain\Contracts;

use Carbon\CarbonImmutable;
use Modules\Reporting\Domain\ValueObjects\ProgramSessionReportDigestData;

/**
 * تجميع تقارير الحصص المُرسَلة عبر فترة زمنية، مصنَّفة حسب البرنامج.
 *
 * يخدم أربعة استخدامات: أرشيف اليوم، الأرشيف الشهري، نافذة تاريخ مخصَّصة،
 * وتوليد الرسالة الشهرية المجمَّعة لكل برنامج.
 */
interface ProgramSessionReportDigestQueries
{
    /**
     * @return list<ProgramSessionReportDigestData> بلا ترتيب مضمون سوى تصاعدي بوقت الإرسال
     */
    public function forOrganizationInRange(
        string $organizationId,
        CarbonImmutable $fromUtc,
        CarbonImmutable $untilUtcExclusive,
        ?string $programId = null,
    ): array;
}
