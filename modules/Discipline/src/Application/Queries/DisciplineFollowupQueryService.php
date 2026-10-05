<?php

declare(strict_types=1);

namespace Modules\Discipline\Application\Queries;

use Carbon\CarbonImmutable;
use Modules\Discipline\Domain\Contracts\DisciplineFollowupQueries;
use Modules\Discipline\Domain\Models\DisciplineAction;
use Modules\Discipline\Domain\Models\ReactivationRequest;
use Modules\Discipline\Domain\Models\ViolationEvent;
use Modules\Discipline\Domain\ValueObjects\FollowupDisciplineData;

final readonly class DisciplineFollowupQueryService implements DisciplineFollowupQueries
{
    public function forEnrollments(string $organizationId, array $enrollmentIds, CarbonImmutable $from, CarbonImmutable $until): array
    {
        if ($enrollmentIds === []) {
            return [];
        }
        $violations = ViolationEvent::query()->forOrganization($organizationId)->whereIn('enrollment_id', $enrollmentIds)
            ->where('occurred_at', '>=', $from)->where('occurred_at', '<', $until)->orderByDesc('occurred_at')->get()->groupBy('enrollment_id');
        $actions = DisciplineAction::query()->forOrganization($organizationId)->whereIn('enrollment_id', $enrollmentIds)->orderByDesc('applied_at')->get()->groupBy('enrollment_id');
        $requests = ReactivationRequest::query()->forOrganization($organizationId)->whereIn('enrollment_id', $enrollmentIds)->orderByDesc('created_at')->get()->groupBy('enrollment_id');
        $result = [];
        foreach ($enrollmentIds as $id) {
            $result[$id] = new FollowupDisciplineData(
                ($violations->get($id) ?? collect())->map(static fn (ViolationEvent $row): array => [
                    'id' => (string) $row->id, 'type' => $row->type->value, 'label' => $row->type->label(),
                    'at' => $row->occurred_at->toIso8601String(), 'countable' => $row->is_countable && !$row->isWaived(),
                    'waived_at' => $row->waived_at?->toIso8601String(), 'reason' => $row->waiver_reason,
                    'actor_id' => $row->waived_by, 'session_id' => $row->session_id,
                ])->all(),
                ($actions->get($id) ?? collect())->map(static fn (DisciplineAction $row): array => [
                    'id' => (string) $row->id, 'label' => $row->action->label(), 'at' => $row->applied_at->toIso8601String(),
                    'automatic' => $row->is_automatic, 'actor_id' => $row->applied_by, 'reason' => $row->notes,
                ])->all(),
                ($requests->get($id) ?? collect())->map(static fn (ReactivationRequest $row): array => [
                    'id' => (string) $row->id, 'status' => $row->status->value, 'label' => $row->status->label(),
                    'open' => $row->isOpen(), 'attempt' => $row->attempt_number, 'at' => $row->created_at?->toIso8601String(),
                    'actor_id' => $row->requested_by, 'statement' => $row->student_statement, 'decision_note' => $row->decision_note,
                    'assessment_attempt_id' => $row->assessment_attempt_id,
                ])->all(),
            );
        }

        return $result;
    }
}
