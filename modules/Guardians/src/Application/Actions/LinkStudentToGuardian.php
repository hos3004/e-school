<?php

declare(strict_types=1);

namespace Modules\Guardians\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Guardians\Domain\Enums\GuardianRelationship;
use Modules\Guardians\Domain\Events\GuardianLinkedToStudent;
use Modules\Guardians\Domain\Models\GuardianLink;
use Modules\Guardians\Domain\Models\GuardianProfile;
use Shared\Support\BusinessRuleViolation;

/**
 * ربط وصي بطالب.
 *
 * قواعد العمل (أرقامها كلها من الإعدادات لا من الكود):
 *  - لا تكرار لزوج (وصي، طالب).
 *  - حدّ أقصى للأوصياء على الطالب: guardians.limits.max_links_per_student.
 *  - حدّ أقصى للطلاب على الوصي: guardians.limits.max_students_per_guardian.
 *  - واصي أساسي واحد فقط للطالب — الأساسية السابقة تُخفَّض تلقائيًا.
 *  - الأقسام المرئية تُرشَّح ضمن القائمة المسموحة في الإعدادات.
 */
final readonly class LinkStudentToGuardian
{
    /**
     * @param  array{
     *     relationship: GuardianRelationship,
     *     is_primary?: bool,
     *     can_act_for?: bool,
     *     visible_sections?: ?list<string>,
     * } $data
     */
    public function execute(string $guardianProfileId, string $studentProfileId, array $data): GuardianLink
    {
        /** @var GuardianProfile $guardian */
        $guardian = GuardianProfile::query()->findOrFail($guardianProfileId);

        $duplicate = GuardianLink::query()
            ->forGuardian($guardianProfileId)
            ->forStudent($studentProfileId)
            ->exists();

        if ($duplicate) {
            throw BusinessRuleViolation::make(
                'guardians.link_already_exists',
                'guardians::errors.link_already_exists',
                ['student_profile_id' => $studentProfileId],
            );
        }

        $maxPerStudent = (int) config('guardians.limits.max_links_per_student');

        $linksForStudent = GuardianLink::query()
            ->forStudent($studentProfileId)
            ->count();

        if ($linksForStudent >= $maxPerStudent) {
            throw BusinessRuleViolation::make(
                'guardians.max_links_per_student_reached',
                'guardians::errors.max_links_per_student_reached',
                ['max' => $maxPerStudent],
            );
        }

        $maxStudentsPerGuardian = (int) config('guardians.limits.max_students_per_guardian');

        $studentsForGuardian = GuardianLink::query()
            ->forGuardian($guardianProfileId)
            ->count();

        if ($studentsForGuardian >= $maxStudentsPerGuardian) {
            throw BusinessRuleViolation::make(
                'guardians.max_students_per_guardian_reached',
                'guardians::errors.max_students_per_guardian_reached',
                ['max' => $maxStudentsPerGuardian],
            );
        }

        $isPrimary = (bool) ($data['is_primary'] ?? false);
        $canActFor = (bool) ($data['can_act_for'] ?? false);
        $visibleSections = $this->filterSections($data['visible_sections'] ?? null);

        /** @var GuardianLink $link */
        $link = DB::transaction(function () use ($guardianProfileId, $studentProfileId, $data, $isPrimary, $canActFor, $visibleSections): GuardianLink {
            if ($isPrimary) {
                GuardianLink::query()
                    ->forStudent($studentProfileId)
                    ->primary()
                    ->update(['is_primary' => false]);
            }

            return GuardianLink::query()->create([
                'guardian_profile_id' => $guardianProfileId,
                'student_profile_id' => $studentProfileId,
                'relationship' => $data['relationship'],
                'is_primary' => $isPrimary,
                'can_act_for' => $canActFor,
                'visible_sections' => $visibleSections,
                'verified_at' => null,
            ]);
        });

        event(new GuardianLinkedToStudent(
            guardianLinkId: $link->id,
            guardianProfileId: $link->guardian_profile_id,
            studentProfileId: $link->student_profile_id,
            relationship: $link->relationship,
            isPrimary: $link->is_primary,
            canActFor: $link->can_act_for,
        ));

        return $link;
    }

    /**
     * @param ?list<string> $sections
     * @return list<string>
     */
    private function filterSections(?array $sections): array
    {
        if ($sections === null || $sections === []) {
            /** @var list<string> $defaults */
            $defaults = config('guardians.links.default_visible_sections', []);

            return $defaults;
        }

        /** @var list<string> $allowed */
        $allowed = config('guardians.links.allowed_visible_sections', []);

        return array_values(array_intersect($sections, $allowed));
    }
}
