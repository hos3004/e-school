<?php

declare(strict_types=1);

namespace Modules\Sessions\Domain\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Sessions\Domain\Enums\ApologyStatus;

/**
 * سُلَّم متابعة اعتذارات المعلم — مراقبة وتصعيد، **لا عقاب**.
 *
 * قاعدة العميل 2026-08-22 (docs/client-answers.md §ك) حرفيًا:
 * لا فصل ولا إقصاء ولا تغيير حالة المعلم آليًا مهما تكرر الاعتذار.
 * أقصى ما يفعله النظام: تنبيه، ثم تحذير، ثم إنشاء تصعيد للإدارة.
 * القرار النهائي يدوي دائمًا.
 *
 * **النافذة متحركة لا شهرية.** المرة الثانية «خلال آخر 30 يومًا» تعني
 * 30 يومًا من لحظة الاعتماد، لا منذ أول الشهر الميلادي. اعتذار عمره 31 يومًا
 * لا يُحتسب — وهذا بالضبط ما يميّز النافذة المتحركة عن الشهرية، وكان
 * التوثيق القديم يقول عكسه فبطل.
 *
 * طول النافذة والسُلَّم من config/discipline.php — ممنوع أي رقم هنا.
 */
final readonly class ApologyEscalationEvaluator
{
    /**
     * ماذا يستحق هذا الاعتذار من إجراء إخطاري؟
     *
     * @return array{
     *     occurrence: int,
     *     window_days: int,
     *     action: string,
     *     severity: string,
     *     notify: list<string>,
     *     creates_escalation: bool,
     *     translation_key: string|null
     * }
     */
    public function evaluate(string $staffProfileId, CarbonImmutable $at): array
    {
        $windowDays = $this->windowDays();
        $occurrence = $this->countInWindow($staffProfileId, $at, $windowDays) + 1;

        $rung = $this->rungFor($occurrence);

        return [
            'occurrence' => $occurrence,
            'window_days' => $windowDays,
            'action' => (string) ($rung['action'] ?? 'record'),
            'severity' => (string) ($rung['severity'] ?? 'info'),
            'notify' => array_values(array_map('strval', (array) ($rung['notify'] ?? []))),
            'creates_escalation' => (bool) ($rung['creates_escalation'] ?? false),
            'translation_key' => isset($rung['translation_key']) ? (string) $rung['translation_key'] : null,
        ];
    }

    /**
     * عدد الاعتذارات المحتسَبة داخل النافذة المتحركة المنتهية عند $at.
     *
     * نعدّ بـ decided_at لا submitted_at: الاعتذار يدخل السُلَّم لحظة اعتماده،
     * والمرفوض أو المسحوب لا أثر تشغيلي له فلا يُحتسب.
     */
    public function countInWindow(string $staffProfileId, CarbonImmutable $at, ?int $windowDays = null): int
    {
        $windowDays ??= $this->windowDays();
        $since = $at->subDays($windowDays);

        return DB::table('teacher_apologies')
            ->where('staff_profile_id', $staffProfileId)
            ->whereNull('deleted_at')
            ->whereIn('status', $this->countableStatuses())
            ->whereNotNull('decided_at')
            // نافذة نصف مفتوحة (since, at] — اعتذار عمره بالضبط طول النافذة خارجها.
            ->where('decided_at', '>', $since)
            ->where('decided_at', '<=', $at)
            ->count();
    }

    public function windowDays(): int
    {
        return max(1, (int) config('discipline.teacher.counter_window_days', 30));
    }

    /**
     * أعلى درجة في السُلَّم ينطبق حدّها على هذه المرتبة.
     *
     * @return array<string, mixed>
     */
    private function rungFor(int $occurrence): array
    {
        /** @var list<array<string, mixed>> $ladder */
        $ladder = (array) config('discipline.teacher.ladder', []);

        $matched = [];

        foreach ($ladder as $rung) {
            $threshold = (int) ($rung['threshold'] ?? 0);

            if ($occurrence >= $threshold) {
                $matched = $rung;
            }
        }

        return $matched;
    }

    /**
     * @return list<string>
     */
    private function countableStatuses(): array
    {
        return array_values(array_map(
            static fn (ApologyStatus $s): string => $s->value,
            array_filter(
                ApologyStatus::cases(),
                static fn (ApologyStatus $s): bool => $s->countsTowardEscalation(),
            ),
        ));
    }
}
