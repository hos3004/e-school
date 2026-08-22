<?php

declare(strict_types=1);

namespace Modules\Guardians\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Guardians\Domain\Events\GuardianLinkUpdated;
use Modules\Guardians\Domain\Models\GuardianLink;
use Shared\Support\BusinessRuleViolation;

/**
 * تعديل رابط وصي قائم: صلة القرابة، الوساطة، الأقسام المرئية.
 */
final readonly class UpdateGuardianLink
{
    /**
     * @param  array{
     *     relationship?: GuardianRelationship,
     *     can_act_for?: bool,
     *     visible_sections?: ?list<string>,
     * } $data
     */
    public function execute(string $guardianLinkId, array $data): GuardianLink
    {
        /** @var GuardianLink $link */
        $link = GuardianLink::query()->findOrFail($guardianLinkId);

        $allowed = array_intersect_key($data, array_flip([
            'relationship',
            'can_act_for',
            'visible_sections',
        ]));

        if ($allowed === []) {
            throw BusinessRuleViolation::make(
                'guardians.nothing_to_update',
                'guardians::errors.nothing_to_update',
                ['guardian_link_id' => $guardianLinkId],
            );
        }

        if (array_key_exists('visible_sections', $allowed) && is_array($allowed['visible_sections'])) {
            /** @var list<string> $allowedSections */
            $allowedSections = config('guardians.links.allowed_visible_sections', []);

            $allowed['visible_sections'] = array_values(
                array_intersect($allowed['visible_sections'], $allowedSections),
            );
        }

        $changes = [];

        foreach ($allowed as $field => $value) {
            /** @var mixed $current */
            $current = $link->getAttribute($field);
            $currentValue = $current instanceof \BackedEnum ? $current->value : $current;
            $newValue = $value instanceof \BackedEnum ? $value->value : $value;

            if ($currentValue !== $newValue) {
                $changes[$field] = ['old' => $currentValue, 'new' => $newValue];
            }
        }

        DB::transaction(function () use ($link, $allowed): void {
            $link->fill($allowed)->save();
        });

        if ($changes !== []) {
            event(new GuardianLinkUpdated(
                guardianLinkId: $link->id,
                guardianProfileId: $link->guardian_profile_id,
                studentProfileId: $link->student_profile_id,
                changes: $changes,
            ));
        }

        return $link->refresh();
    }
}
