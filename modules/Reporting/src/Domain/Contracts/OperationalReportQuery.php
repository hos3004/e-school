<?php

declare(strict_types=1);

namespace Modules\Reporting\Domain\Contracts;

use Modules\Reporting\Domain\ValueObjects\OperationalReportCriteria;
use Modules\Reporting\Domain\ValueObjects\OperationalReportData;

interface OperationalReportQuery
{
    public function run(OperationalReportCriteria $criteria): OperationalReportData;

    /**
     * @return array{students: array<string, string>, teachers: array<string, string>, groups: array<string, string>, courses: array<string, string>}
     */
    public function options(OperationalReportCriteria $criteria): array;

    /**
     * أسماء المعرّفات المحددة فقط؛ مناسبة للتصدير حتى عندما تكون النتيجة فارغة.
     *
     * @return array{students: array<string, string>, teachers: array<string, string>, groups: array<string, string>, courses: array<string, string>}
     */
    public function selectedOptions(OperationalReportCriteria $criteria): array;
}
