<?php

declare(strict_types=1);

namespace Modules\Guardians\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Guardians\Domain\Events\GuardianLinkUpdated;
use Modules\Guardians\Domain\Models\GuardianLink;

/**
 * تعيين رابط كوصي أساسي للطالب — واصي أساسي واحد فقط في كل لحظة.
 */
final readonly class SetPrimaryGuardianLink
{
    public function __construct(
        private AuditRecorder $audit,
    ) {}

    public function execute(
        string $guardianLinkId,
        ?string $actorId = null,
        ?string $reason = null,
    ): GuardianLink {
        /** @var GuardianLink $link */
        $link = GuardianLink::query()->with('guardian')->findOrFail($guardianLinkId);

        if ($link->is_primary) {
            return $link;
        }

        DB::transaction(function () use ($link, $actorId, $reason): void {
            GuardianLink::query()
                ->forStudent($link->student_profile_id)
                ->primary()
                ->update(['is_primary' => false]);

            $link->forceFill(['is_primary' => true])->save();

            if ($actorId !== null && $reason !== null && trim($reason) !== '') {
                $this->audit->record(
                    organizationId: (string) $link->guardian->organization_id,
                    actorId: $actorId,
                    actorType: 'user',
                    action: 'guardians.primary_link_changed',
                    auditableType: 'guardian_link',
                    auditableId: (string) $link->getKey(),
                    oldValues: ['is_primary' => false],
                    newValues: ['is_primary' => true],
                    reason: $reason,
                );
            }
        });

        event(new GuardianLinkUpdated(
            guardianLinkId: $link->id,
            guardianProfileId: $link->guardian_profile_id,
            studentProfileId: $link->student_profile_id,
            changes: ['is_primary' => ['old' => false, 'new' => true]],
        ));

        return $link->refresh();
    }
}
