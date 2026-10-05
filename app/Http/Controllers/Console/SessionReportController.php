<?php

declare(strict_types=1);

namespace App\Http\Controllers\Console;

use App\Application\Queries\RecordingAccessCoordinator;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Modules\Academics\Domain\Contracts\AcademicCatalogQueries;
use Modules\Attendance\Domain\Contracts\AttendanceAdministrationQueries;
use Modules\Attendance\Domain\Enums\AttendanceStatus;
use Modules\Groups\Domain\Contracts\GroupAdministrationQueries;
use Modules\Recordings\Domain\Contracts\RecordingAdministrationQueries;
use Modules\Recordings\Domain\Enums\RecordingStatus;
use Modules\Sessions\Domain\Contracts\SessionAdministrationQueries;
use Modules\Sessions\Domain\Contracts\SessionParticipantAdministrationQueries;
use Modules\Sessions\Domain\Enums\SessionStatus;
use Modules\Staff\Domain\Contracts\StaffQueries;
use Modules\Students\Domain\Contracts\StudentDirectoryQueries;
use Shared\Support\LocalizedJsonColumn;

/**
 * تقرير حصة واحدة للوحة الإدارة: متى تمّت فعليًا، والحضور التفصيلي،
 * وروابط معاينة التسجيلات الموقّعة، والمعلم والطلاب.
 *
 * قراءة فقط عبر العقود العامة للموديولات — لا وصول مباشر لجداول موديول آخر.
 */
final class SessionReportController extends Controller
{
    public function __invoke(
        Request $request,
        string $session,
        SessionAdministrationQueries $sessions,
        SessionParticipantAdministrationQueries $participants,
        AttendanceAdministrationQueries $attendance,
        RecordingAdministrationQueries $recordings,
        RecordingAccessCoordinator $recordingAccess,
        StaffQueries $staff,
        StudentDirectoryQueries $students,
        AcademicCatalogQueries $catalog,
        GroupAdministrationQueries $groups,
    ): JsonResponse {
        $user = $request->user();
        abort_if($user === null, 401);
        $organizationId = (string) data_get($user, 'organization_id');
        abort_if($organizationId === '', 403);

        $record = $sessions->findForOrganization($organizationId, $session);
        abort_if($record === null, 404);

        $timezone = (string) (data_get($user, 'timezone') ?: config('app.timezone'));
        $format = static fn (?string $value): ?string => $value === null
            ? null
            : CarbonImmutable::parse($value)->setTimezone($timezone)->toIso8601String();

        $courseNames = $catalog->coursesByIds($organizationId, [$record->courseId]);
        $groupNames = $record->groupId === '' ? [] : $groups->groupsByIds($organizationId, [$record->groupId]);
        $teacherIds = array_values(array_unique(array_filter([$record->staffProfileId, $record->originalStaffProfileId])));
        $teacherNames = $staff->namesForProfiles($organizationId, $teacherIds);

        $rows = $participants->forSession($organizationId, $session);
        $participantIds = array_map(static fn ($row): string => $row->id, $rows);
        $studentNames = $students->namesForProfiles(
            $organizationId,
            array_values(array_unique(array_map(static fn ($row): string => $row->studentProfileId, $rows))),
        );
        $attendanceByParticipant = $attendance->byParticipantIds($organizationId, $participantIds);

        $attendanceRows = array_map(function ($row) use ($attendanceByParticipant, $studentNames): array {
            $entry = $attendanceByParticipant[$row->id] ?? null;
            $status = $entry !== null ? AttendanceStatus::tryFrom($entry->status) : null;

            return [
                'student_id' => $row->studentProfileId,
                'student' => $studentNames[$row->studentProfileId] ?? __('console.not_set'),
                'status' => $entry?->status,
                'status_label' => $status?->label() ?? ($row->excusedAt !== null ? __('attendance::status.excused') : __('console.not_set')),
                'attended_minutes' => $entry->attendedMinutes ?? $row->attendedMinutes,
                'joined_after_minutes' => $entry->joinedAfterMinutes ?? null,
                'left_before_minutes' => $entry->leftBeforeMinutes ?? null,
                'first_joined_at' => $row->firstJoinedAt,
                'last_left_at' => $row->lastLeftAt,
                'override_reason' => $entry->overrideReason ?? null,
                'excused' => $row->excusedAt !== null,
            ];
        }, $rows);

        $recordingRows = array_map(function ($recording) use ($user, $recordingAccess): array {
            $status = RecordingStatus::tryFrom($recording->status);
            $canWatch = $recordingAccess->canWatch($user, $recording);

            return [
                'id' => $recording->id,
                'status' => $recording->status,
                'status_label' => $status?->label() ?? $recording->status,
                'duration_minutes' => $recording->durationSeconds === null ? null : (int) round($recording->durationSeconds / 60),
                'available_from' => $recording->availableFrom,
                'expires_at' => $recording->expiresAt,
                'view_count' => $recording->viewCount,
                'preview_url' => $canWatch && $status?->isWatchable() === true
                    ? URL::temporarySignedRoute(
                        'portal.recordings.watch',
                        now()->addMinutes(max(1, (int) config('recordings.access.signed_url_ttl_minutes'))),
                        ['recording' => $recording->id],
                    )
                    : null,
            ];
        }, $recordings->forSession($organizationId, $session));

        return response()->json([
            'session' => [
                'id' => $record->id,
                'title' => LocalizedJsonColumn::display($record->title),
                'status' => $record->status,
                'status_label' => SessionStatus::tryFrom($record->status)?->label() ?? $record->status,
                'course' => isset($courseNames[$record->courseId])
                    ? LocalizedJsonColumn::display($courseNames[$record->courseId]->name)
                    : __('console.not_set'),
                'group' => isset($groupNames[$record->groupId])
                    ? LocalizedJsonColumn::display($groupNames[$record->groupId]->name)
                    : null,
                'teacher' => $teacherNames[$record->staffProfileId] ?? __('console.unassigned'),
                'original_teacher' => $record->originalStaffProfileId === null
                    || $record->originalStaffProfileId === $record->staffProfileId
                        ? null
                        : ($teacherNames[$record->originalStaffProfileId] ?? __('console.not_set')),
                'scheduled_start' => $format($record->scheduledStart),
                'scheduled_end' => $format($record->scheduledEnd),
                'actual_start' => $format($record->actualStart),
                'actual_end' => $format($record->actualEnd),
                'finalized_at' => $format($record->finalizedAt),
                'timezone' => $timezone,
            ],
            'attendance' => $attendanceRows,
            'recordings' => $recordingRows,
        ]);
    }
}
