<?php

declare(strict_types=1);

namespace App\Http\Controllers\Learning;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Support\PortalData;
use App\Http\Requests\Learning\RequestScheduleChangeRequest;
use App\Http\Requests\Learning\RespondToScheduleChangeRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Modules\Scheduling\Application\Actions\RequestScheduleChange;
use Modules\Scheduling\Application\Actions\RespondToScheduleChange;
use Modules\Scheduling\Application\Actions\WithdrawScheduleChange;
use Modules\Scheduling\Domain\Models\Schedule;
use Modules\Scheduling\Domain\Models\ScheduleChangeRequest;

/**
 * بوابة التعلم: طلب المعلم تغيير الموعد الدائم ورد الطالب عليه.
 *
 * الصلاحية تُفحص في middleware وفي Policy، وارتباط الفاعل بالجدول أو بالطلب
 * تفرضه الـActions في الموديول؛ إخفاء زر في الواجهة ليس حماية.
 */
final class LearningScheduleChangeController extends Controller
{
    public function __construct(
        private readonly PortalData $data,
        private readonly RequestScheduleChange $requestChange,
        private readonly RespondToScheduleChange $respondToChange,
        private readonly WithdrawScheduleChange $withdrawChange,
    ) {}

    public function store(RequestScheduleChangeRequest $request, string $schedule): RedirectResponse
    {
        $user = $request->user();
        $organizationId = (string) $user?->getAttribute('organization_id');
        $actorId = (string) $user?->getAuthIdentifier();
        $staffProfileId = $this->data->staffProfileId($actorId, $organizationId);
        abort_if($organizationId === '' || $staffProfileId === null, 403);

        $record = Schedule::query()
            ->forOrganization($organizationId)
            ->whereKey($schedule)
            ->first();
        abort_if($record === null, 404);
        Gate::authorize('create', ScheduleChangeRequest::class);

        $validated = $request->validated();
        $this->requestChange->execute(
            schedule: $record,
            staffProfileId: $staffProfileId,
            requestedBy: $actorId,
            proposedSlots: $validated['slots'],
            intervalWeeks: isset($validated['interval_weeks']) ? (int) $validated['interval_weeks'] : null,
            reason: (string) $validated['reason'],
        );

        return back()->with('success', __('scheduling::messages.schedule_change_requested'));
    }

    public function respond(RespondToScheduleChangeRequest $request, string $change): RedirectResponse
    {
        $user = $request->user();
        $organizationId = (string) $user?->getAttribute('organization_id');
        $actorId = (string) $user?->getAuthIdentifier();
        $studentProfileId = $this->data->studentProfileId($actorId, $organizationId);
        abort_if($organizationId === '' || $studentProfileId === null, 403);

        $record = $this->find($organizationId, $change);
        Gate::authorize('respond', $record);

        $validated = $request->validated();
        $accepted = $validated['decision'] === 'accept';
        $this->respondToChange->execute(
            request: $record,
            studentProfileId: $studentProfileId,
            respondedBy: $actorId,
            accepted: $accepted,
            note: isset($validated['note']) ? (string) $validated['note'] : null,
        );

        return back()->with('success', __(
            $accepted
                ? 'scheduling::messages.schedule_change_accepted'
                : 'scheduling::messages.schedule_change_declined',
        ));
    }

    public function withdraw(Request $request, string $change): RedirectResponse
    {
        $user = $request->user();
        $organizationId = (string) $user?->getAttribute('organization_id');
        $actorId = (string) $user?->getAuthIdentifier();
        $staffProfileId = $this->data->staffProfileId($actorId, $organizationId);
        abort_if($organizationId === '' || $staffProfileId === null, 403);

        $record = $this->find($organizationId, $change);
        Gate::authorize('withdraw', $record);
        abort_unless((string) $record->staff_profile_id === $staffProfileId, 403);

        $this->withdrawChange->execute($record, $actorId);

        return back()->with('success', __('scheduling::messages.schedule_change_withdrawn'));
    }

    private function find(string $organizationId, string $change): ScheduleChangeRequest
    {
        $record = ScheduleChangeRequest::query()
            ->forOrganization($organizationId)
            ->whereKey($change)
            ->first();
        abort_if($record === null, 404);

        return $record;
    }
}
