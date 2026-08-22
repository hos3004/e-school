<?php

declare(strict_types=1);

namespace Modules\Guardians\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Guardians\Domain\Events\GuardianProfileUpdated;
use Modules\Guardians\Domain\Models\GuardianProfile;
use Shared\Support\BusinessRuleViolation;

/**
 * تحديث بيانات ملف وصي قائم.
 */
final readonly class UpdateGuardianProfile
{
    /**
     * @param  array{
     *     national_id_last4?: ?string,
     *     occupation?: ?string,
     *     preferred_contact_channel?: ?string,
     * } $data
     */
    public function execute(string $guardianProfileId, array $data): GuardianProfile
    {
        $profile = GuardianProfile::query()->findOrFail($guardianProfileId);

        $allowed = array_intersect_key($data, array_flip([
            'national_id_last4',
            'occupation',
            'preferred_contact_channel',
        ]));

        if ($allowed === []) {
            throw BusinessRuleViolation::make(
                'guardians.nothing_to_update',
                'guardians::errors.nothing_to_update',
                ['guardian_profile_id' => $guardianProfileId],
            );
        }

        $changes = [];

        foreach ($allowed as $field => $value) {
            /** @var mixed $current */
            $current = $profile->getAttribute($field);
            $newValue = $value instanceof \BackedEnum ? $value->value : $value;

            if (($current instanceof \BackedEnum ? $current->value : $current) !== $newValue) {
                $changes[$field] = ['old' => $current instanceof \BackedEnum ? $current->value : $current, 'new' => $newValue];
            }
        }

        DB::transaction(function () use ($profile, $allowed): void {
            $profile->fill($allowed)->save();
        });

        if ($changes !== []) {
            event(new GuardianProfileUpdated(
                guardianProfileId: $profile->id,
                changes: $changes,
            ));
        }

        return $profile->refresh();
    }
}
