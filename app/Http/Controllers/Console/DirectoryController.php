<?php

declare(strict_types=1);

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class DirectoryController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        abort_if($user === null, 401);
        $definitions = [
            ['before', 'courses', '/manage/courses', ['course.manage']],
            ['before', 'programs', '/manage/courses/programs', ['program.manage']],
            ['before', 'groups', '/manage/groups', ['group.view']],
            ['before', 'registration', '/manage/registration', ['student.create']],
            ['before', 'forms', '/manage/registration?stage=forms', ['student.create']],
            ['before', 'placement', '/manage/placement', ['student.view.any', 'enrollment.create', 'group.manage']],
            ['before', 'student_create', '/manage/students/create', ['student.create']],
            ['before', 'teacher_create', '/manage/teachers/create', ['staff.create']],
            ['quran', 'quran_before', '/manage/quran?phase=before', ['student.view.any']],
            ['quran', 'quran_during', '/manage/quran?phase=during', ['student.view.any']],
            ['quran', 'quran_after', '/manage/quran?phase=after', ['student.view.any']],
            ['during', 'today', '/manage', ['admin.panel.access']],
            ['during', 'calendar', '/manage/sessions', ['session.view', 'student.view.any']],
            ['during', 'followup', '/manage/followup', ['student.view.any', 'enrollment.view', 'attendance.view', 'discipline.view_any']],
            ['during', 'returns', '/manage/followup?filter=held', ['student.view.any', 'enrollment.view', 'attendance.view', 'discipline.view_any']],
            ['after', 'reports', '/manage/reports', ['report.view']],
            ['after', 'missing_reports', '/manage/reports?report_status=missing#report-details', ['report.view']],
            ['after', 'dues', '/manage/teacher-dues', ['payroll.view']],
            ['records', 'students', '/manage/students', ['student.view.any']],
            ['records', 'teachers', '/manage/teachers', ['staff.view.any']],
            ['records', 'groups', '/manage/groups', ['group.view']],
            ['settings', 'school', '/manage/settings#settings-school', ['organizations.view']],
            ['settings', 'accounts', '/manage/settings#settings-accounts', ['organizations.view']],
            ['settings', 'study', '/manage/settings#settings-study', ['organizations.view']],
            ['settings', 'notifications', '/manage/settings#settings-notifications', ['organizations.view']],
            ['settings', 'portal', '/learn/entry', ['admin.panel.access']],
        ];
        /** @var array<string, array{key: string, title: string, items: list<array{key: string, label: string, href: string, legacy: bool}>}> $sections */
        $sections = [];
        foreach ($definitions as [$group, $key, $href, $permissions]) {
            if (($key === 'dues' && !(bool) config('features.payroll'))
                || !collect($permissions)->every(fn (string $permission): bool => $user->can($permission))) {
                continue;
            }
            $this->appendItem($sections, $group, $key, (string) __('console_directory.items.'.$key), $href);
        }
        $replaced = ['CourseFilamentResource', 'ProgramFilamentResource', 'LevelFilamentResource', 'GroupResource',
            'RegistrationFormResource', 'RegistrationQuestionResource', 'RegistrationApplicationResource', 'StudentProfileResource',
            'StaffProfileResource', 'PayrollAdjustmentResource', 'PayrollEntryResource', 'OrganizationFilamentResource',
            'AcademicCalendarFilamentResource', 'HolidayFilamentResource', 'NotificationCategorySettingResource'];
        $previousPanel = Filament::getCurrentPanel();
        $panel = Filament::getPanel('admin');
        Filament::setCurrentPanel($panel);
        try {
            foreach ($panel->getResources() as $resource) {
                if (in_array(class_basename($resource), $replaced, true) || !$resource::canViewAny()) {
                    continue;
                }
                $this->appendItem($sections, 'specialized', class_basename($resource), $resource::getPluralModelLabel(), $resource::getUrl('index', panel: 'admin'), true);
            }

        } finally {
            Filament::setCurrentPanel($previousPanel);
        }

        return Inertia::render('Console/Directory', ['sections' => array_values($sections), 'search' => mb_substr($request->string('search')->toString(), 0, 120)]);
    }

    /**
     * @param array<string, array{key: string, title: string, items: list<array{key: string, label: string, href: string, legacy: bool}>}> $sections
     */
    private function appendItem(array &$sections, string $group, string $key, string $label, string $href, bool $legacy = false): void
    {
        $sections[$group] ??= ['key' => $group, 'title' => (string) __('console_directory.groups.'.$group), 'items' => []];
        $sections[$group]['items'][] = ['key' => $key, 'label' => $label, 'href' => $href, 'legacy' => $legacy];
    }
}
