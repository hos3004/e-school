<?php

declare(strict_types=1);

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Http\Requests\Console\SaveConsoleSettingRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Notifications\Domain\Models\NotificationCategorySetting;
use Modules\Organization\Application\Actions\ActivateAcademicCalendar;
use Modules\Organization\Application\Actions\AddHoliday;
use Modules\Organization\Application\Actions\CloseAcademicCalendar;
use Modules\Organization\Application\Actions\CreateAcademicCalendar;
use Modules\Organization\Application\Actions\RemoveHoliday;
use Modules\Organization\Application\Actions\UpsertOrganizationSetting;
use Modules\Organization\Domain\Models\AcademicCalendar;
use Modules\Organization\Domain\Models\Holiday;
use Modules\Organization\Domain\Models\Organization;
use Shared\Support\BusinessRuleViolation;

final class SettingsOperationsController extends Controller
{
    public function __invoke(SaveConsoleSettingRequest $request, string $operation, ConsoleSettingsData $settings, AuditRecorder $audit): RedirectResponse
    {
        $data = $request->validated();
        $organizationId = (string) data_get($request->user(), 'organization_id');
        try {
            DB::transaction(function () use ($request, $operation, $settings, $audit, $data, $organizationId): void {
                $organization = Organization::query()->lockForUpdate()->findOrFail($organizationId);
                $before = [];
                $after = [];
                $subjectType = Organization::class;
                $subjectId = $organizationId;
                if ($operation === 'accounts') {
                    Gate::authorize('manageSettings', $organization);
                    $before = $settings->accounts($organizationId);
                    $this->assertVersion($before['version'], $data['version']);
                    app(UpsertOrganizationSetting::class)->execute($organization, (string) config('admission.username.organization_setting_key'), $data['username_prefix'] ?? null);
                    $after = $settings->accounts($organizationId);
                } elseif ($operation === 'notifications') {
                    Gate::authorize('viewAny', NotificationCategorySetting::class);
                    $before = collect($settings->notificationCategories($organizationId))->firstWhere('category', $data['category']);
                    abort_if(!is_array($before), 404);
                    $this->assertVersion($before['version'], $data['version']);
                    $record = NotificationCategorySetting::query()->forOrganization($organizationId)->firstOrNew(['category' => $data['category']]);
                    $record->organization_id = $organizationId;
                    Gate::authorize('update', $record);
                    $record->fill([
                        'channels' => $data['channels'], 'is_critical' => $data['is_critical'],
                        'respects_quiet_hours' => $data['respects_quiet_hours'],
                    ])->save();
                    $subjectType = NotificationCategorySetting::class;
                    $subjectId = $record->id;
                    $after = $record->only(['category', 'channels', 'is_critical', 'respects_quiet_hours']);
                    $before = array_intersect_key($before, $after);
                } elseif ($operation === 'calendar-create') {
                    Gate::authorize('create', AcademicCalendar::class);
                    $record = app(CreateAcademicCalendar::class)->execute($organization, ['ar' => $data['name']], $data['starts_on'], $data['ends_on']);
                    $subjectType = AcademicCalendar::class;
                    $subjectId = $record->id;
                    $after = $record->getAttributes();
                } elseif (in_array($operation, ['calendar-activate', 'calendar-close'], true)) {
                    $record = AcademicCalendar::query()->forOrganization($organizationId)->lockForUpdate()->findOrFail($data['id']);
                    $this->assertVersion(ConsoleSettingsData::calendarFingerprint($record), $data['version']);
                    Gate::authorize($operation === 'calendar-activate' ? 'activate' : 'close', $record);
                    $subjectType = AcademicCalendar::class;
                    $subjectId = $record->id;
                    $before = ['active_calendar_ids' => AcademicCalendar::query()->forOrganization($organizationId)->active()->pluck('id')->all()];
                    if ($operation === 'calendar-activate') {
                        app(ActivateAcademicCalendar::class)->execute($record);
                    } else {
                        app(CloseAcademicCalendar::class)->execute($record);
                    }
                    $after = ['active_calendar_ids' => AcademicCalendar::query()->forOrganization($organizationId)->active()->pluck('id')->all()];
                } elseif ($operation === 'holiday-create') {
                    Gate::authorize('create', Holiday::class);
                    if (!empty($data['academic_calendar_id'])) {
                        abort_unless($request->user()?->can('academic_calendars.view_any'), 403);
                        AcademicCalendar::query()->forOrganization($organizationId)->findOrFail($data['academic_calendar_id']);
                    }
                    $record = app(AddHoliday::class)->execute($organizationId, ['ar' => $data['name']], $data['starts_on'], $data['ends_on'], $data['academic_calendar_id'] ?? null, $data['blocks_scheduling']);
                    $subjectType = Holiday::class;
                    $subjectId = $record->id;
                    $after = $record->getAttributes();
                } else {
                    $record = Holiday::query()->forOrganization($organizationId)->lockForUpdate()->findOrFail($data['id']);
                    $this->assertVersion(ConsoleSettingsData::fingerprint($record->getAttributes()), $data['version']);
                    Gate::authorize('delete', $record);
                    $subjectType = Holiday::class;
                    $subjectId = $record->id;
                    $before = $record->getAttributes();
                    app(RemoveHoliday::class)->execute($record);
                }
                if ($before !== $after) {
                    $audit->record($organizationId, (string) $request->user()?->getAuthIdentifier(), 'user',
                        'console.settings.'.$operation, $subjectType, $subjectId, $before, $after,
                        (string) __('console_settings.audit.'.$operation));
                }
            });
        } catch (BusinessRuleViolation $exception) {
            throw ValidationException::withMessages(['settings' => $exception->getMessage()]);
        }

        return to_route('console.settings')->with('success', __('console_settings.saved'));
    }

    private function assertVersion(string $current, string $submitted): void
    {
        if (!hash_equals($current, $submitted)) {
            throw ValidationException::withMessages(['version' => __('console.settings.concurrent')]);
        }
    }
}
