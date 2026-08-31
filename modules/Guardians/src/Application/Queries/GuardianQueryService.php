<?php

declare(strict_types=1);

namespace Modules\Guardians\Application\Queries;

use Carbon\CarbonInterface;
use Modules\Guardians\Domain\Contracts\GuardianQuery;
use Modules\Guardians\Domain\Models\GuardianLink;
use Modules\Guardians\Domain\Models\GuardianProfile;
use Modules\Identity\Domain\Contracts\DTOs\UserSummary;
use Modules\Identity\Domain\Contracts\UserAccountDirectory;
use Modules\Identity\Domain\Contracts\UserQueryService;

/**
 * تنفيذ قراءة فقط — لا كتابة، ولا كشف لنماذج Eloquent.
 */
final readonly class GuardianQueryService implements GuardianQuery
{
    public function __construct(
        private UserAccountDirectory $accounts,
        private UserQueryService $users,
    ) {}

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

    public function guardianOptions(string $organizationId, string $search = ''): array
    {
        /** @var list<GuardianProfile> $profiles */
        $profiles = GuardianProfile::query()
            ->forOrganization($organizationId)
            ->orderBy('created_at')
            ->limit(500)
            ->get()
            ->all();

        $summaries = $this->users->summariesByIds(
            array_map(static fn (GuardianProfile $profile): string => (string) $profile->user_id, $profiles),
        );

        $options = [];
        foreach ($profiles as $profile) {
            /** @var UserSummary|null $summary */
            $summary = $summaries[(string) $profile->user_id] ?? null;
            $name = $summary->name ?? (string) $profile->user_id;

            if ($search !== '' && mb_stripos($name, $search) === false) {
                continue;
            }

            $options[(string) $profile->getKey()] = $this->label($organizationId, $name, $profile);
        }

        return $options;
    }

    public function guardianLabel(string $organizationId, string $guardianProfileId): ?string
    {
        /** @var GuardianProfile|null $profile */
        $profile = GuardianProfile::query()
            ->forOrganization($organizationId)
            ->whereKey($guardianProfileId)
            ->first();

        if ($profile === null) {
            return null;
        }

        $summary = $this->users->findSummary((string) $profile->user_id);

        return $this->label($organizationId, $summary->name ?? (string) $profile->user_id, $profile);
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

    private function label(string $organizationId, string $name, GuardianProfile $profile): string
    {
        $contact = $this->accounts->find($organizationId, (string) $profile->user_id);
        $detail = $contact->phone ?? $contact->email ?? '';

        return trim($name.' · '.$detail, ' ·');
    }

    private function toSummary(GuardianLink $link): GuardianSummary
    {
        /** @var GuardianProfile $guardian */
        $guardian = $link->getRelationValue('guardian');

        return new GuardianSummary(
            guardianLinkId: $link->id,
            guardianProfileId: $link->guardian_profile_id,
            userId: $guardian->user_id,
            relationship: $link->relationship,
            isPrimary: $link->is_primary,
            canActFor: $link->can_act_for,
            verifiedAt: $this->format($link->verified_at),
            visibleSections: $link->visible_sections ?? [],
            preferredContactChannel: $guardian->preferred_contact_channel,
        );
    }

    private function format(?CarbonInterface $date): ?string
    {
        return $date?->toIso8601String();
    }
}
