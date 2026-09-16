<?php

declare(strict_types=1);

namespace Modules\VirtualClassroom\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\VirtualClassroom\Domain\Contracts\VirtualClassroomProvider;
use Modules\VirtualClassroom\Domain\Enums\ClassroomHealthStatus;
use Modules\VirtualClassroom\Domain\Enums\ClassroomStatus;
use Modules\VirtualClassroom\Domain\Exceptions\ClassroomProviderException;
use Modules\VirtualClassroom\Domain\Models\Classroom;
use Modules\VirtualClassroom\Domain\ValueObjects\RoomIdentity;
use Shared\Support\BusinessRuleViolation;

/**
 * تدوير رابط الغرفة الدائمة — إجراء إداري بحت.
 *
 * لا يتحقق هذا الإجراء من صلاحية المستدعي؛ الصفحة/المتحكّم الذي يستدعيه
 * هو من يتأكد أن الفاعل إداري (نفس الصلاحية التي تحمي إعدادات الفصل
 * المباشر: virtual-classroom.health_check.alert_permission) قبل الوصول هنا.
 *
 * الأثر: الرابط والغرفة القديمان يُبطلان فورًا — الجيل الجديد يرفض أي رابط
 * محمول بجيل أقدم قبل حتى إعادة إنشاء الغرفة عند المزوّد، والغرفة القديمة
 * تُنهى إن كانت لا تزال قائمة. الحصة القادمة على هذا الجدول تُنشئ غرفة
 * ومفاتيح جديدة تلقائيًا عند أول دخول لها، دون أي تدخّل إضافي.
 */
final readonly class RotateClassroomLinkAction
{
    public function __construct(
        private VirtualClassroomProvider $provider,
        private AuditRecorder $audit,
    ) {}

    public function execute(
        string $organizationId,
        string $scheduleId,
        string $actorId,
        string $reason,
    ): Classroom {
        $roomIdentity = RoomIdentity::forSchedule($scheduleId);

        return DB::transaction(function () use ($organizationId, $roomIdentity, $actorId, $reason): Classroom {
            /** @var Classroom|null $classroom */
            $classroom = Classroom::query()
                ->forRoomIdentity($roomIdentity)
                ->lockForUpdate()
                ->first();

            if ($classroom === null) {
                throw BusinessRuleViolation::make(
                    'virtualclassroom.room_not_found',
                    'virtualclassroom::errors.room_not_found',
                );
            }

            $previousExternalId = $classroom->external_id;
            $previousModeratorSecret = $classroom->moderator_secret;
            $newGeneration = (int) $classroom->link_generation + 1;

            if ($previousExternalId !== null) {
                try {
                    $this->provider->endClassroom($previousExternalId, $previousModeratorSecret);
                } catch (ClassroomProviderException) {
                    // الغرفة القديمة قد تكون منتهية أصلًا عند المزوّد؛ هذا وحده لا يمنع التدوير.
                }
            }

            $before = [
                'external_id' => $previousExternalId,
                'generation' => $classroom->link_generation,
            ];

            $classroom->forceFill([
                'link_generation' => $newGeneration,
                'external_id' => null,
                'moderator_secret' => null,
                'attendee_secret' => null,
                'external_meta' => null,
                'status' => ClassroomStatus::Pending,
                'health_status' => ClassroomHealthStatus::Unknown,
                'started_at' => null,
                'ended_at' => null,
                'last_error' => null,
                'last_error_at' => null,
                'rotated_at' => now('UTC'),
                'rotated_by' => $actorId,
            ])->save();

            $this->audit->record(
                organizationId: $organizationId,
                actorId: $actorId,
                actorType: 'user',
                action: 'virtualclassroom.link_rotated',
                auditableType: 'classrooms',
                auditableId: (string) $classroom->getKey(),
                oldValues: $before,
                newValues: ['generation' => $newGeneration],
                reason: $reason,
            );

            return $classroom;
        });
    }
}
