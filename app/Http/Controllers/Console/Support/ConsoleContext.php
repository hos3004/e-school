<?php

declare(strict_types=1);

namespace App\Http\Controllers\Console\Support;

use Illuminate\Http\Request;
use Modules\Organization\Domain\Models\Organization;

final readonly class ConsoleContext
{
    /** @return array<string, mixed> */
    public function forRequest(Request $request): array
    {
        $user = $request->user();
        $organization = $user === null ? null : Organization::query()->find((string) data_get($user, 'organization_id'));
        $navigation = [
            ['today', '/manage', 'work', 'admin.panel.access'],
            ['courses', '/manage/courses', 'work', 'course.manage'],
            ['quran', '/manage/quran', 'work', 'student.view.any'],
            ['placement', '/manage/placement', 'work', ['student.view.any', 'enrollment.create', 'group.manage']],
            ['registration', '/manage/registration', 'work', 'student.create'],
            ['teacher_dues', '/manage/teacher-dues', 'work', 'payroll.view'],
            ['followup', '/manage/followup', 'work', ['student.view.any', 'enrollment.view', 'attendance.view', 'discipline.view_any']],
            ['students', '/manage/students', 'records', 'student.view.any'],
            ['teachers', '/manage/teachers', 'records', 'staff.view.any'],
            ['groups', '/manage/groups', 'records', 'group.view'],
            ['sessions', '/manage/sessions', 'records', ['session.view', 'student.view.any']],
            ['reports', '/manage/reports', 'work', 'report.view'],
            ['settings', '/manage/settings', 'tools', 'organizations.view'],
            ['directory', '/manage/directory', 'tools', 'admin.panel.access'],
            ['portal', '/learn/entry', 'tools', 'admin.panel.access'],
        ];
        if ((bool) config('console.enabled') && (bool) config('console.primary')) {
            $navigation[] = ['legacy', '/v2', 'tools', 'admin.panel.access'];
        }
        $items = [];
        foreach ($navigation as [$key, $href, $group, $ability]) {
            if ($key === 'teacher_dues' && !(bool) config('features.payroll')) {
                continue;
            }
            if (collect((array) $ability)->every(fn (string $permission): bool => (bool) $user?->can($permission))) {
                $items[] = ['key' => $key, 'href' => $href, 'group' => $group, 'label' => __('console.nav.'.$key), 'fullPage' => $key === 'legacy'];
            }
        }

        $schoolTimezone = $organization === null
            ? (string) config('app.timezone')
            : (string) $organization->default_timezone;

        return [
            'navigation' => $items,
            'school' => [
                'name' => $organization?->name[app()->getLocale()] ?? $organization?->name['ar'] ?? config('app.name'),
                'timezone' => $schoolTimezone,
            ],
            'timezone' => data_get($user, 'timezone') ?: $schoolTimezone,
            'local' => app()->environment(['local', 'testing']),
        ];
    }
}
