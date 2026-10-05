<?php

declare(strict_types=1);

namespace Modules\Guardians\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Guardians\Domain\Events\GuardianUnlinkedFromStudent;
use Modules\Guardians\Domain\Models\GuardianLink;

/**
 * فكّ رابط وصي عن طالب مع الاحتفاظ بالسجل عبر SoftDeletes.
 */
final readonly class UnlinkStudentFromGuardian
{
    public function __construct(
        private AuditRecorder $audit,
    ) {}

    public function execute(string $guardianLinkId, string $reason, ?string $actorId = null): void
    {
        /** @var GuardianLink $link */
        $link = GuardianLink::query()->with('guardian')->findOrFail($guardianLinkId);

        DB::transaction(function () use ($link, $reason, $actorId): void {
            if ($actorId !== null) {
                $this->audit->record(
                    organizationId: (string) $link->guardian->organization_id,
                    actorId: $actorId,
                    actorType: 'user',
                    action: 'guardians.student_unlinked',
                    auditableType: 'guardian_link',
                    auditableId: (string) $link->getKey(),
                    oldValues: [
                        'guardian_profile_id' => (string) $link->guardian_profile_id,
                        'student_profile_id' => (string) $link->student_profile_id,
                        'relationship' => $link->relationship->value,
                    ],
                    newValues: ['archived' => true],
                    reason: $reason,
                );
            }

            $link->delete();
        });

        event(new GuardianUnlinkedFromStudent(
            guardianLinkId: $link->id,
            guardianProfileId: $link->guardian_profile_id,
            studentProfileId: $link->student_profile_id,
            reason: $reason,
        ));
    }
}
