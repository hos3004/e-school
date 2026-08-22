<?php

declare(strict_types=1);

namespace Modules\Discipline\Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Discipline\Domain\Enums\ReactivationStatus;
use Modules\Discipline\Domain\Enums\ViolationType;
use Modules\Discipline\Domain\Models\DisciplineAction;
use Modules\Discipline\Domain\Models\ReactivationRequest;
use Modules\Discipline\Domain\Models\ViolationEvent;
use Modules\Discipline\Domain\Services\EscalationLadder;
use Modules\Discipline\Domain\ValueObjects\DisciplineWindow;

/**
 * بيانات تجريبية لموديول الانضباط.
 *
 * ينشئ مخالفات شهرية لطلاب تجريبيين ثم يشتق قيود الإجراءات منها عبر
 * محرّك السُلَّم نفسه — لا أرقام سياسة هنا إطلاقًا.
 */
final class DisciplineSeeder extends Seeder
{
    public function run(): void
    {
        $organizationId = $this->ensureOrganizationId();

        // ثلاثة طلاب تجريبيون بمستويات مختلفة: 1 و2 و3 مخالفات هذا الشهر.
        foreach ([1, 2, 3] as $index => $violationCount) {
            $enrollmentId = sprintf('01JDEMOENROLL%010d', $index + 1);
            $studentProfileId = sprintf('01JDEMOSTUDENT0%09d', $index + 1);

            for ($i = 0; $i < $violationCount; $i++) {
                $occurredAt = CarbonImmutable::now('UTC')->subDays($violationCount - $i);

                $violation = ViolationEvent::query()->create([
                    'organization_id' => $organizationId,
                    'enrollment_id' => $enrollmentId,
                    'student_profile_id' => $studentProfileId,
                    'session_id' => null,
                    'type' => ViolationType::UnexcusedAbsence,
                    'occurred_at' => $occurredAt,
                    'window_key' => DisciplineWindow::forDate($occurredAt)->key,
                    'is_countable' => true,
                ]);

                $resolved = (new EscalationLadder)->resolveForCount($i + 1);

                if ($resolved !== null
                    && !DisciplineAction::query()
                        ->where('enrollment_id', $enrollmentId)
                        ->where('window_key', $violation->window_key)
                        ->where('action', $resolved['action']->value)
                        ->exists()
                ) {
                    DisciplineAction::query()->create([
                        'organization_id' => $organizationId,
                        'enrollment_id' => $enrollmentId,
                        'triggered_by_event_id' => (string) $violation->getKey(),
                        'action' => $resolved['action'],
                        'threshold_reached' => $resolved['threshold_reached'],
                        'window_key' => $violation->window_key,
                        'is_automatic' => $resolved['automatic'],
                        'applied_at' => $occurredAt,
                        'applied_by' => null,
                    ]);
                }
            }
        }

        // طلب إعادة تفعيل واحد بانتظار المراجعة — يتطلب مستخدمًا موجودًا (قيد FK).
        $demoUserId = DB::table('users')->orderBy('created_at')->value('id');

        if (is_string($demoUserId) && $demoUserId !== '') {
            ReactivationRequest::query()->firstOrCreate(
                ['enrollment_id' => '01JDEMOENROLL0000000003'],
                [
                    'organization_id' => $organizationId,
                    'requested_by' => $demoUserId,
                    'status' => ReactivationStatus::Pending,
                    'attempt_number' => 1,
                    'student_statement' => __('discipline::messages.demo_reactivation_statement'),
                ],
            );
        }
    }

    private function ensureOrganizationId(): string
    {
        $existing = DB::table('organizations')->orderBy('created_at')->value('id');

        if (is_string($existing) && $existing !== '') {
            return $existing;
        }

        return '01JDEMOORGANIZATION0000000';
    }
}
