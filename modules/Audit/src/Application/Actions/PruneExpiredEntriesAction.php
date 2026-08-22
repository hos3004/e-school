<?php

declare(strict_types=1);

namespace Modules\Audit\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Modules\Audit\Domain\Events\AuditEntriesPruned;
use Modules\Audit\Domain\Models\AuditLog;

/**
 * تقادم دفتر التدقيق — حذف القيود الأقدم من مدة الاحتفاظ.
 *
 * مدة الاحتفاظ بالأيام من config('audit.retention_days') — لا رقم داخل الكود.
 * هذا الاستثناء الوحيد على قاعدة «لا حذف»، وهو دوري ومقيّد بالتقادم فقط.
 *
 * الترتيب الإلزامي: حراس ← DB::transaction ← نشر الحدث بعد النجاح.
 */
final readonly class PruneExpiredEntriesAction
{
    public function execute(): int
    {
        $retentionDays = max(1, (int) config('audit.retention_days', 730));
        $before = CarbonImmutable::now('UTC')->subDays($retentionDays);
        $beforeIso = $before->toIso8601String();

        $pruned = DB::transaction(function () use ($before): int {
            return AuditLog::query()
                ->where('created_at', '<', $before)
                ->delete();
        });

        if ($pruned > 0) {
            Event::dispatch(new AuditEntriesPruned(
                prunedCount: $pruned,
                beforeDate: $beforeIso,
            ));
        }

        return $pruned;
    }
}
