<?php

declare(strict_types=1);

namespace Modules\Guardians\Application\Queries;

use Carbon\CarbonInterface;
use Modules\Guardians\Domain\Contracts\GuardianQuery;
use Modules\Guardians\Domain\Models\GuardianLink;

/**
 * تنفيذ قراءة فقط — لا كتابة، ولا كشف لنماذج Eloquent.
 */
final readonly class GuardianQueryService implements GuardianQuery
{
    public function primaryGuardianForStudent(string $studentProfileId): ?GuardianSummary
    {
        /** @var GuardianLink|null $link */
        $link = GuardianLink::query()
            ->forStudent($studentProfileId)
            ->primary()
            ->with('guardian')
            ->first();

        return $link === null ? null : $this->toSummary($link);
    }

    public function guardiansForStudent(string $studentProfileId): array
    {
        return GuardianLink::query()
            ->forStudent($studentProfileId)
            ->with('guardian')
            ->orderByDesc('is_primary')
            ->orderBy('created_at')
            ->get()
            ->map(fn (GuardianLink $link): GuardianSummary => $this->toSummary($link))
            ->all();
    }

    public function userCanActForStudent(string $userId, string $studentProfileId): bool
    {
        $requiresVerification = (bool) config('guardians.links.require_verification_for_acting', true);

        /** @var GuardianLink|null $link */
        $link = GuardianLink::query()
            ->forStudent($studentProfileId)
            ->whereHas('guardian', function ($query) use ($userId): void {
                $query->where('user_id', $userId);
            })
            ->where('can_act_for', true)
            ->when($requiresVerification, fn ($query) => $query->verified())
            ->first();

        return $link !== null;
    }

    private function toSummary(GuardianLink $link): GuardianSummary
    {
        /** @var \Modules\Guardians\Domain\Models\GuardianProfile|null $guardian */
        $guardian = $link->getRelationValue('guardian');

        return new GuardianSummary(
            guardianLinkId: $link->id,
            guardianProfileId: $link->guardian_profile_id,
            userId: $guardian?->user_id ?? '',
            relationship: $link->relationship,
            isPrimary: $link->is_primary,
            canActFor: $link->can_act_for,
            verifiedAt: $this->format($link->verified_at),
            visibleSections: $link->visible_sections ?? [],
            preferredContactChannel: $guardian?->preferred_contact_channel,
        );
    }

    private function format(?CarbonInterface $date): ?string
    {
        return $date?->toIso8601String();
    }
}
