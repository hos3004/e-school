<?php

declare(strict_types=1);

namespace Modules\Scheduling\Application\Actions;

use Carbon\CarbonImmutable;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Scheduling\Application\Services\PostponementRecipientResolver;
use Modules\Scheduling\Domain\Enums\PostponementStatus;
use Modules\Scheduling\Domain\Events\PostponementRequested;
use Modules\Scheduling\Domain\Models\PostponementRequest;
use Modules\Sessions\Domain\Contracts\SessionAdministrationQueries;
use Modules\Sessions\Domain\Contracts\SessionSchedulingQueries;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

final readonly class RequestPostponement
{
    public function __construct(
        private Transaction $transaction,
        private SessionSchedulingQueries $sessions,
        private SessionAdministrationQueries $sessionAdministration,
        private AuditRecorder $audit,
        private PostponementRecipientResolver $recipients,
    ) {}

    public function execute(
        string $organizationId,
        string $sessionId,
        string $requestedBy,
        ?string $studentProfileId,
        CarbonImmutable $proposedStart,
        string $reason,
        ?string $requestingStaffProfileId = null,
    ): PostponementRequest {
        $reason = trim($reason);
        if ($reason === '') {
            throw BusinessRuleViolation::make('scheduling.reason_required', 'scheduling::errors.reason_required');
        }

        $session = $this->sessions->find($organizationId, $sessionId);
        if ($session === null) {
            throw BusinessRuleViolation::make('session.not_found', 'scheduling::errors.session_not_found');
        }
        if ($requestingStaffProfileId !== null) {
            $administrationSession = $this->sessionAdministration->findForOrganization($organizationId, $sessionId);
            $assignedStaff = array_filter([
                $session->staffProfileId,
                $administrationSession?->originalStaffProfileId,
            ]);

            if (!in_array($requestingStaffProfileId, $assignedStaff, true)) {
                throw BusinessRuleViolation::make('postponement.teacher_not_assigned', 'scheduling::errors.teacher_not_assigned_to_session');
            }
        }
        if ($requestingStaffProfileId === null && ($studentProfileId === null || !in_array($studentProfileId, $session->studentProfileIds, true))) {
            throw BusinessRuleViolation::make('postponement.student_not_participant', 'scheduling::errors.student_not_participant');
        }
        if (!in_array($session->status, ['scheduled', 'confirmed'], true)) {
            throw BusinessRuleViolation::make(
                'session.not_postponable',
                'scheduling::errors.session_not_postponable',
                ['status' => $session->status],
            );
        }

        $required = (int) config('scheduling.notice.postponement_minutes');
        $actual = (int) CarbonImmutable::now('UTC')->diffInMinutes($session->scheduledStart, false);
        if ($actual < $required) {
            throw BusinessRuleViolation::make(
                'postponement.notice_not_met',
                'scheduling::errors.postponement_notice_not_met',
                ['required' => $required, 'actual' => max($actual, 0)],
            );
        }
        if ($proposedStart->lessThanOrEqualTo(CarbonImmutable::now('UTC'))) {
            throw BusinessRuleViolation::make('postponement.proposed_start_in_past', 'scheduling::errors.proposed_start_in_past');
        }
        if (PostponementRequest::query()->forOrganization($organizationId)
            ->where('session_id', $sessionId)
            ->whereIn('status', [PostponementStatus::Requested->value, PostponementStatus::AlternativeProposed->value])
            ->exists()) {
            throw BusinessRuleViolation::make('postponement.already_pending', 'scheduling::errors.postponement_already_pending');
        }

        $requiresAdminReview = false;
        $request = $this->transaction->run(function () use (
            $organizationId,
            $sessionId,
            $requestedBy,
            $studentProfileId,
            $proposedStart,
            $reason,
            $requiresAdminReview,
        ): PostponementRequest {
            $request = PostponementRequest::query()->create([
                'organization_id' => $organizationId,
                'session_id' => $sessionId,
                'requested_by' => $requestedBy,
                'requested_for_student_id' => $studentProfileId,
                'status' => PostponementStatus::Requested,
                'requires_admin_review' => $requiresAdminReview,
                'proposed_start' => $proposedStart,
                'reason' => $reason,
                'expires_at' => CarbonImmutable::now('UTC')->addHours(
                    (int) config('scheduling.postponement.teacher_response_sla_hours'),
                ),
            ]);
            $this->audit->record(
                organizationId: $organizationId,
                actorId: $requestedBy,
                actorType: 'user',
                action: 'scheduling.postponement_requested',
                auditableType: 'postponement_requests',
                auditableId: (string) $request->getKey(),
                oldValues: null,
                newValues: [
                    'session_id' => $sessionId,
                    'student_profile_id' => $studentProfileId,
                    'status' => PostponementStatus::Requested->value,
                    'proposed_start' => $proposedStart->toIso8601String(),
                    'requires_admin_review' => $requiresAdminReview,
                ],
                reason: $reason,
            );

            return $request;
        });

        $recipients = $this->recipients->forSession($organizationId, $sessionId, $studentProfileId);
        event(new PostponementRequested(
            requestId: (string) $request->getKey(),
            sessionId: $sessionId,
            studentProfileId: $studentProfileId,
            proposedStart: $proposedStart->toIso8601String(),
            organizationId: $organizationId,
            studentUserIds: $recipients['student_user_ids'],
            teacherUserId: $recipients['teacher_user_id'],
            actorId: $requestedBy,
        ));

        return $request;
    }
}
