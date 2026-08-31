<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\DTO\BulkPlacementCandidate;
use App\Application\DTO\BulkPlacementPreflight;
use App\Application\DTO\BulkPlacementResult;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Groups\Domain\Contracts\GroupAdministrationQueries;
use Modules\Groups\Domain\Contracts\GroupProvisioningGateway;
use Modules\Students\Domain\Contracts\StudentAdmissionQueries;
use Modules\Students\Domain\ValueObjects\AdmissionCandidateData;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * تسكين جماعي لطلاب مقبولين في مجموعة قائمة أو مجموعة مسودة جديدة.
 *
 * يعيش في جذر التركيب لا داخل موديول: الرحلة تمس Students وGroups وEnrollments
 * وAcademics معًا، وموديول Students في الطبقة ١ لا يجوز أن يعتمد على Groups
 * في الطبقة ٢. كل تعامل هنا عبر العقود العامة و DTOs — لا نموذج Eloquent.
 *
 * السلوك الحاكم: **الكل أو لا شيء**. فشل طالب واحد أثناء الحفظ يُرجع المعاملة
 * كاملة، فلا تبقى مجموعة جديدة بلا طلاب ولا قيد بلا عضوية.
 *
 * الفحص المسبق (`preflight`) للعرض فقط ولا يكتب شيئًا؛ التنفيذ يعيد كل
 * الفحوص داخل المعاملة مع قفل المجموعة، لأن السعة قد تتغير بين الشاشتين.
 */
final readonly class BulkAssignStudentsToGroupAction
{
    public function __construct(
        private StudentAdmissionQueries $admissions,
        private GroupAdministrationQueries $groups,
        private GroupProvisioningGateway $provisioning,
        private AssignStudentToGroupAction $assign,
        private Transaction $transaction,
        private AuditRecorder $audit,
    ) {}

    /**
     * فحص مسبق: من يصلح للتسكين ومن لا، ولماذا.
     *
     * @param list<string> $applicationIds المعرّفات كما وصلت من الجدول — لا يُوثق بها
     */
    public function preflight(
        string $actorOrganizationId,
        array $applicationIds,
        ?string $groupId,
    ): BulkPlacementPreflight {
        $candidates = $this->admissions->placementCandidates($actorOrganizationId, $applicationIds);
        $group = $groupId === null ? null : $this->groups->openGroupForPlacement($actorOrganizationId, $groupId);
        $existingMemberIds = $group === null ? [] : $this->memberProfileIds($actorOrganizationId, $group->id);

        $verdicts = array_map(
            fn (AdmissionCandidateData $candidate): BulkPlacementCandidate => $this->verdictFor(
                $candidate,
                $existingMemberIds,
            ),
            $candidates,
        );

        $eligibleCount = count(array_filter(
            $verdicts,
            static fn (BulkPlacementCandidate $verdict): bool => $verdict->eligible,
        ));

        return new BulkPlacementPreflight(
            candidates: array_values($verdicts),
            remainingSeats: $group?->remainingSeats,
            groupLabel: $group === null ? null : $this->groupLabel($group->name, $group->code),
            groupIsDraft: $group?->isDraft() ?? true,
            capacityWarning: $group !== null && $eligibleCount > $group->remainingSeats
                ? __('students::admin.bulk_placement.capacity_warning', [
                    'eligible' => $eligibleCount,
                    'remaining' => $group->remainingSeats,
                ])
                : null,
        );
    }

    /**
     * التنفيذ الذري.
     *
     * @param list<string> $applicationIds
     * @param array<string, string>|null $newGroupName اسم المجموعة الجديدة بحسب اللغة
     */
    public function execute(
        string $actorOrganizationId,
        array $applicationIds,
        string $programId,
        ?string $courseId,
        ?string $groupId,
        ?array $newGroupName,
        string $timezone,
        string $reason,
        ?string $actorId = null,
        ?string $correlationId = null,
    ): BulkPlacementResult {
        $reason = trim($reason);

        if ($reason === '') {
            throw BusinessRuleViolation::make(
                'enrollments.placement_reason_required',
                'enrollments::errors.placement_reason_required',
            );
        }

        if ($groupId === null && ($newGroupName === null || $newGroupName === [])) {
            throw BusinessRuleViolation::make(
                'placement.group_target_required',
                'students::errors.bulk_placement_group_target_required',
            );
        }

        return $this->transaction->run(function () use (
            $actorOrganizationId,
            $applicationIds,
            $programId,
            $courseId,
            $groupId,
            $newGroupName,
            $timezone,
            $reason,
            $actorId,
            $correlationId,
        ): BulkPlacementResult {
            // إعادة الفحص داخل المعاملة: ما رآه المستخدم قد تغيّر منذ ذلك الحين.
            $preflight = $this->preflight($actorOrganizationId, $applicationIds, $groupId);

            if (!$preflight->hasWork()) {
                throw BusinessRuleViolation::make(
                    'placement.no_eligible_students',
                    'students::errors.bulk_placement_no_eligible_students',
                );
            }

            $groupWasCreated = false;

            if ($groupId === null) {
                /** @var array<string, string> $newGroupName */
                $draft = $this->provisioning->createDraft(
                    organizationId: $actorOrganizationId,
                    name: $newGroupName,
                    programId: $programId,
                    timezone: $timezone,
                    reason: $reason,
                    actorId: $actorId,
                );

                $groupId = $draft->groupId;
                $groupWasCreated = true;
            }

            $group = $this->groups->openGroupForPlacement($actorOrganizationId, $groupId);

            if ($group === null) {
                throw BusinessRuleViolation::make(
                    'groups.group_not_found',
                    'groups::errors.group_not_found',
                );
            }

            $eligible = $preflight->eligible();

            if (count($eligible) > $group->remainingSeats) {
                throw BusinessRuleViolation::make(
                    'groups.capacity_reached',
                    'groups::errors.capacity_reached',
                    ['capacity' => $group->capacity ?? $group->remainingSeats],
                );
            }

            $placed = [];

            foreach ($eligible as $candidate) {
                /*
                 * كل طالب يمر بالمنسّق الفردي نفسه: أهلية البرنامج، السياق
                 * الأكاديمي، القيد الرسمي، ثم العضوية. أي خرق يرمي استثناء
                 * فترتد المعاملة كاملة — لا نجاح جزئي.
                 */
                $this->assign->execute(
                    actorOrganizationId: $actorOrganizationId,
                    studentProfileId: (string) $candidate->studentProfileId,
                    programId: $programId,
                    groupId: $group->id,
                    courseId: $courseId,
                    actorId: $actorId,
                    correlationId: $correlationId,
                    reason: $reason,
                );

                $placed[] = (string) $candidate->studentProfileId;
            }

            $this->audit->record(
                organizationId: $actorOrganizationId,
                actorId: $actorId,
                actorType: 'user',
                action: 'enrollment.bulk_placed',
                auditableType: 'groups',
                auditableId: $group->id,
                oldValues: [
                    'group_created' => false,
                    'occupied_seats' => $group->occupiedSeats,
                ],
                newValues: [
                    'group_created' => $groupWasCreated,
                    'group_status' => $group->status,
                    'program_id' => $programId,
                    'course_id' => $courseId,
                    // معرّفات ملفات لا بيانات شخصية ولا إجابات تسجيل.
                    'student_profile_ids' => $placed,
                    'placed_count' => count($placed),
                    'skipped_existing_count' => count($preflight->alreadyMembers()),
                ],
                reason: $reason,
                correlationId: $correlationId,
            );

            return new BulkPlacementResult(
                groupId: $group->id,
                groupLabel: $this->groupLabel($group->name, $group->code),
                groupWasCreated: $groupWasCreated,
                groupIsDraft: $group->isDraft(),
                placedStudentProfileIds: $placed,
                skippedExistingCount: count($preflight->alreadyMembers()),
            );
        });
    }

    /**
     * معرّفات ملفات الطلاب الذين يشغلون مقاعد في المجموعة الآن.
     *
     * @return array<string, true>
     */
    private function memberProfileIds(string $organizationId, string $groupId): array
    {
        $ids = [];

        foreach ($this->groups->membershipsForGroup($organizationId, $groupId) as $member) {
            if ($member->leftAt === null) {
                $ids[$member->studentProfileId] = true;
            }
        }

        return $ids;
    }

    /** @param array<string, true> $existingMemberIds */
    private function verdictFor(
        AdmissionCandidateData $candidate,
        array $existingMemberIds,
    ): BulkPlacementCandidate {
        if ($candidate->studentProfileId === null) {
            return BulkPlacementCandidate::rejected(
                $candidate->applicationId,
                null,
                $candidate->fullName,
                $candidate->displayCode(),
                __('students::admin.bulk_placement.reasons.no_student_profile'),
            );
        }

        if (!$candidate->clearedForAssignment) {
            return BulkPlacementCandidate::rejected(
                $candidate->applicationId,
                $candidate->studentProfileId,
                $candidate->fullName,
                $candidate->displayCode(),
                __('students::admin.bulk_placement.reasons.status_not_cleared', [
                    'status' => __('students::registration.status.'.$candidate->status),
                ]),
            );
        }

        if (isset($existingMemberIds[$candidate->studentProfileId])) {
            return BulkPlacementCandidate::alreadyMember(
                $candidate->applicationId,
                $candidate->studentProfileId,
                $candidate->fullName,
                $candidate->displayCode(),
                __('students::admin.bulk_placement.reasons.already_member'),
            );
        }

        return BulkPlacementCandidate::eligible(
            $candidate->applicationId,
            $candidate->studentProfileId,
            $candidate->fullName,
            $candidate->displayCode(),
        );
    }

    /** @param array<string, string> $name */
    private function groupLabel(array $name, string $code): string
    {
        $localized = $name[app()->getLocale()] ?? $name['ar'] ?? $name['en'] ?? '';

        return ($localized === '' ? $code : $localized).' · '.$code;
    }
}
