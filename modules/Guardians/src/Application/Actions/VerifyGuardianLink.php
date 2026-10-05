<?php

declare(strict_types=1);

namespace Modules\Guardians\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Guardians\Domain\Events\GuardianLinkVerified;
use Modules\Guardians\Domain\Models\GuardianLink;
use Shared\Support\BusinessRuleViolation;

/**
 * توثيق رابط وصي — إثبات أن صلة القرابة صحيحة بعد مراجعة الإدارة.
 *
 * التوثيق شرط للوساطة عندما guardians.links.require_verification_for_acting.
 */
final readonly class VerifyGuardianLink
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

        if ($link->verified_at !== null) {
            throw BusinessRuleViolation::make(
                'guardians.link_already_verified',
                'guardians::errors.link_already_verified',
                ['guardian_link_id' => $guardianLinkId],
            );
        }

        DB::transaction(function () use ($link, $actorId, $reason): void {
            $link->forceFill(['verified_at' => CarbonImmutable::now('UTC')])->save();

            if ($actorId !== null && $reason !== null && trim($reason) !== '') {
                $this->audit->record(
                    organizationId: (string) $link->guardian->organization_id,
                    actorId: $actorId,
                    actorType: 'user',
                    action: 'guardians.link_verified',
                    auditableType: 'guardian_link',
                    auditableId: (string) $link->getKey(),
                    oldValues: ['verified_at' => null],
                    newValues: ['verified_at' => $link->verified_at?->toIso8601String()],
                    reason: $reason,
                );
            }
        });

        event(new GuardianLinkVerified(
            guardianLinkId: $link->id,
            guardianProfileId: $link->guardian_profile_id,
            studentProfileId: $link->student_profile_id,
        ));

        return $link->refresh();
    }
}
