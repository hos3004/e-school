<?php

declare(strict_types=1);

namespace Modules\Reporting\Domain\Contracts;

use Shared\Archive\ClosureSnapshot;

/**
 * بناء حصيلة الإقفال لكيان قبل أرشفته.
 *
 * مكانها Reporting (الطبقة 7) لأنها وحدها تقرأ من كل الموديولات: حصيلة برنامج
 * تجمع بنيته الأكاديمية وقيوده وحصصه. لو بُنيت داخل Academics لاحتاج موديولٌ
 * في الطبقة 2 أن يعرف موديولات التشغيل فوقه، وهو اتجاه اعتماد ممنوع.
 *
 * ترجع لقطةً وموانع؛ ولا تُقفل شيئًا بنفسها — القرار والحارس في أكشن المجال.
 */
interface ClosureSnapshotQueries
{
    public function forProgram(string $organizationId, string $programId): ClosureSnapshot;

    public function forLevel(string $organizationId, string $levelId): ClosureSnapshot;

    public function forCourse(string $organizationId, string $courseId): ClosureSnapshot;

    public function forGroup(string $organizationId, string $groupId): ClosureSnapshot;
}
