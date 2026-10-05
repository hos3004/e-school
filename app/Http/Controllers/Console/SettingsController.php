<?php

declare(strict_types=1);

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Http\Requests\Console\UpdateSchoolSettingsRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Organization\Application\Actions\UpdateOrganization;
use Modules\Organization\Domain\Models\Organization;

final class SettingsController extends Controller
{
    public function index(Request $request, ConsoleSettingsData $settingsData): Response
    {
        $organization = Organization::query()->findOrFail((string) data_get($request->user(), 'organization_id'));
        $sections = [
            'accounts' => ['self_registration' => 'admission.self_registration.enabled'],
            'registration' => ['registration_limit' => 'admission.self_registration.rate_limit_per_minute'],
            'study' => ['postponement_notice' => 'scheduling.notice.postponement_minutes', 'student_apology_notice' => 'scheduling.student_apology.min_notice_minutes'],
            'attendance' => ['absence_window' => 'discipline.counter_window_days', 'reactivation_assessment' => 'discipline.reactivation.requires_assessment'],
            'dues' => ['teacher_dues' => 'features.payroll', 'teacher_payouts' => 'features.teacher_payouts'],
            'content' => ['assignments' => 'features.assignments', 'certificates' => 'features.certificates'],
            'notifications' => ['whatsapp' => 'features.whatsapp', 'messaging' => 'features.messaging'],
            'integrations' => ['classroom_provider' => 'virtual-classroom.default'],
        ];
        $policies = [];
        foreach ($sections as $section => $settings) {
            $fields = [];
            foreach ($settings as $label => $key) {
                $value = config($key);
                $fields[] = ['key' => $label, 'label' => __('console.settings.fields.'.$label), 'value' => is_scalar($value) ? $value : null];
            }
            $policies[] = ['key' => $section, 'fields' => $fields];
        }

        return Inertia::render('Console/Settings', [
            'school' => [
                'name' => ['ar' => $organization->name['ar'] ?? ''],
                'default_timezone' => $organization->default_timezone,
                'week_starts_on' => $organization->week_starts_on,
                'version' => $this->version($organization),
            ],
            'timezones' => \DateTimeZone::listIdentifiers(),
            'canUpdate' => (bool) $request->user()?->can('organizations.update'),
            'policies' => $policies,
            ...$settingsData->forOrganization((string) $organization->id, $request->user()),
        ]);
    }

    public function update(UpdateSchoolSettingsRequest $request, UpdateOrganization $update, AuditRecorder $audit): RedirectResponse
    {
        $data = $request->validated();
        $organizationId = (string) data_get($request->user(), 'organization_id');
        DB::transaction(function () use ($request, $update, $audit, $data, $organizationId): void {
            $organization = Organization::query()->lockForUpdate()->findOrFail($organizationId);
            if (!hash_equals($this->version($organization), $data['version'])) {
                throw ValidationException::withMessages(['version' => __('console.settings.concurrent')]);
            }
            $attributes = [
                'name' => [...$organization->name, 'ar' => $data['name']['ar']],
                'default_timezone' => $data['default_timezone'],
                'week_starts_on' => $data['week_starts_on'] ?? $organization->week_starts_on,
            ];
            $before = $organization->only(array_keys($attributes));
            $update->execute($organization, $attributes);
            $after = $organization->refresh()->only(array_keys($attributes));
            if ($before !== $after) {
                $audit->record($organizationId, (string) $request->user()?->getAuthIdentifier(), 'user',
                    'console.school_settings.updated', Organization::class, $organizationId,
                    $before, $after, (string) __('console.settings.audit'));
            }
        });

        return to_route('console.settings')->with('success', __('console.settings.saved'));
    }

    private function version(Organization $organization): string
    {
        return hash('sha256', json_encode(
            $organization->only(['name', 'default_timezone', 'default_locale', 'week_starts_on']),
            JSON_THROW_ON_ERROR,
        ));
    }
}
