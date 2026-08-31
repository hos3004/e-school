<?php

declare(strict_types=1);

namespace Modules\Guardians\Application\Services;

use Modules\Guardians\Application\Actions\LinkStudentToGuardian;
use Modules\Guardians\Application\Actions\UnlinkStudentFromGuardian;
use Modules\Guardians\Domain\Contracts\GuardianLinkOperations;
use Modules\Guardians\Domain\Enums\GuardianRelationship;
use Modules\Guardians\Domain\Models\GuardianLink;
use Modules\Guardians\Domain\Models\GuardianProfile;
use Shared\Support\BusinessRuleViolation;

/**
 * تنفيذ عمليات الروابط داخل موديول Guardians.
 *
 * يتحقق من أن الوصي داخل المؤسسة المطلوبة قبل التفويض؛ رابط فكّ
 * يُتحقق من مؤسسته عبر ملف الوصي المرتبط بالرابط نفسه.
 */
final readonly class GuardianLinkOperationService implements GuardianLinkOperations
{
    public function __construct(
        private LinkStudentToGuardian $linkAction,
        private UnlinkStudentFromGuardian $unlinkAction,
    ) {}

    public function link(
        string $organizationId,
        string $guardianProfileId,
        string $studentProfileId,
        string $relationship,
        bool $isPrimary,
        bool $canActFor,
        ?array $visibleSections,
        string $actorId,
        string $reason,
    ): string {
        if (trim($reason) === '') {
            throw BusinessRuleViolation::make(
                'guardians.link_reason_required',
                'guardians::errors.reason_required',
            );
        }

        /** @var GuardianProfile|null $profile */
        $profile = GuardianProfile::query()
            ->forOrganization($organizationId)
            ->whereKey($guardianProfileId)
            ->first();

        if ($profile === null) {
            throw BusinessRuleViolation::make(
                'guardians.guardian_not_in_organization',
                'guardians::errors.guardian_not_found',
                ['guardian_profile_id' => $guardianProfileId],
            );
        }

        $link = $this->linkAction->execute(
            guardianProfileId: $guardianProfileId,
            studentProfileId: $studentProfileId,
            data: [
                'relationship' => GuardianRelationship::from($relationship),
                'is_primary' => $isPrimary,
                'can_act_for' => $canActFor,
                'visible_sections' => $visibleSections,
            ],
            actorId: $actorId,
            reason: trim($reason),
        );

        return (string) $link->getKey();
    }

    public function unlink(
        string $organizationId,
        string $guardianLinkId,
        string $actorId,
        string $reason,
    ): void {
        if (trim($reason) === '') {
            throw BusinessRuleViolation::make(
                'guardians.unlink_reason_required',
                'guardians::errors.reason_required',
            );
        }

        /** @var GuardianLink|null $record */
        $record = GuardianLink::query()->with('guardian')->find($guardianLinkId);

        if ($record === null || (string) ($record->guardian->organization_id ?? '') !== $organizationId) {
            throw BusinessRuleViolation::make(
                'guardians.link_not_in_organization',
                'guardians::errors.guardian_not_found',
                ['guardian_link_id' => $guardianLinkId],
            );
        }

        $this->unlinkAction->execute($guardianLinkId, trim($reason), $actorId);
    }
}
