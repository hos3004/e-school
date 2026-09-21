<?php

declare(strict_types=1);

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Console\Support\ConsoleContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Console\ArchiveClosureRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Academics\Application\Actions\CloseCourseAction;
use Modules\Academics\Application\Actions\CloseProgramAction;
use Modules\Academics\Application\Actions\ReopenCourseAction;
use Modules\Academics\Application\Actions\ReopenProgramAction;
use Modules\Academics\Domain\Models\Course;
use Modules\Academics\Domain\Models\Program;
use Modules\Groups\Application\Actions\CloseGroupAction;
use Modules\Groups\Application\Actions\ReopenGroupAction;
use Modules\Groups\Domain\Models\Group;
use Modules\Identity\Domain\Contracts\UserQueryService;
use Modules\Reporting\Domain\Contracts\ClosureSnapshotQueries;
use Shared\Archive\ClosureSnapshot;
use Shared\Support\LocalizedJsonColumn;

/**
 * الأرشيف — ما أُقفل وخرج من الواجهة اليومية دون أن يفقد شيئًا.
 *
 * الإقفال ليس حذفًا ولا سلة مهملات: الصف باقٍ بكامل ما تحته، ومعه بطاقة حصيلة
 * مجمَّدة وقت الإقفال. سبب الفصل بين المفهومين أن الحذف الناعم يخفي الصف عن كل
 * استعلام، فتعود أرقام الحصيلة أصفارًا — وهي بالضبط ما لا يجوز فقده هنا.
 *
 * الصلاحية تُفحَص على السجل نفسه عبر Policy لا على المستخدم وحده، لأن القرار
 * يتوقف على مؤسسة السجل. وإخفاء زر في React لا يحمي شيئًا: كل مسار هنا يفحص.
 */
final class ArchiveController extends Controller
{
    public function index(Request $request, UserQueryService $users, ConsoleContext $context): Response
    {
        $organizationId = $this->organizationId($request);

        $sections = [
            'programs' => $this->closedRows(Program::query()->forOrganization($organizationId)->closed(), 'program', $request),
            'courses' => $this->closedRows(Course::query()->forOrganization($organizationId)->closed(), 'course', $request),
            'groups' => $this->closedRows(Group::query()->forOrganization($organizationId)->closed(), 'group', $request),
        ];

        $candidates = [
            'programs' => $this->openRows(Program::query()->forOrganization($organizationId)->open(), 'program', $request),
            'courses' => $this->openRows(Course::query()->forOrganization($organizationId)->open(), 'course', $request),
            'groups' => $this->openRows(Group::query()->forOrganization($organizationId)->open(), 'group', $request),
        ];

        return Inertia::render('Console/Archive', [
            'sections' => $this->withActorNames($sections, $users),
            'candidates' => $candidates,
            'timezone' => $context->forRequest($request)['timezone'],
            'abilities' => [
                'programs' => $request->user()?->can('program.manage') ?? false,
                'courses' => $request->user()?->can('course.manage') ?? false,
                'groups' => $request->user()?->can('group.manage') ?? false,
            ],
        ]);
    }

    /**
     * معاينة الحصيلة قبل الإقفال.
     *
     * يرى المستخدم ما سيُجمَّد وما يمنع الإقفال قبل أن يقرر، بدل أن يُصدّ برسالة
     * خطأ بعد الضغط.
     */
    public function preview(Request $request, string $kind, string $id, ClosureSnapshotQueries $snapshots): JsonResponse
    {
        $subject = $this->subject($request, $kind, $id);
        Gate::authorize('view', $subject);

        $snapshot = $this->snapshot($snapshots, $kind, $this->organizationId($request), $id);

        return response()->json([
            'kind' => $kind,
            'summary' => $snapshot->summary,
            'blockers' => $snapshot->blockers,
            'closable' => !$snapshot->isBlocked(),
        ]);
    }

    public function close(
        ArchiveClosureRequest $request,
        string $kind,
        string $id,
        ClosureSnapshotQueries $snapshots,
    ): RedirectResponse {
        $subject = $this->subject($request, $kind, $id);
        Gate::authorize('delete', $subject);

        $actorId = (string) $request->user()?->getAuthIdentifier();
        $snapshot = $this->snapshot($snapshots, $kind, $this->organizationId($request), $id);

        match ($kind) {
            'program' => app(CloseProgramAction::class)->execute($subject, $request->reason(), $snapshot, $actorId),
            'course' => app(CloseCourseAction::class)->execute($subject, $request->reason(), $snapshot, $actorId),
            default => app(CloseGroupAction::class)->execute($subject, $request->reason(), $snapshot, $actorId),
        };

        return back()->with('success', __('console_archive.flash.closed'));
    }

    public function reopen(ArchiveClosureRequest $request, string $kind, string $id): RedirectResponse
    {
        $subject = $this->subject($request, $kind, $id);
        Gate::authorize('restore', $subject);

        $actorId = (string) $request->user()?->getAuthIdentifier();

        match ($kind) {
            'program' => app(ReopenProgramAction::class)->execute($subject, $request->reason(), $actorId),
            'course' => app(ReopenCourseAction::class)->execute($subject, $request->reason(), $actorId),
            default => app(ReopenGroupAction::class)->execute($subject, $request->reason(), $actorId),
        };

        return back()->with('success', __('console_archive.flash.reopened'));
    }

    /**
     * السجل المطلوب داخل مؤسسة المستخدم — 404 خارجها، فلا يكشف المسار وجودها.
     */
    private function subject(Request $request, string $kind, string $id): Program|Course|Group
    {
        $organizationId = $this->organizationId($request);

        $subject = match ($kind) {
            'program' => Program::query()->forOrganization($organizationId)->whereKey($id)->first(),
            'course' => Course::query()->forOrganization($organizationId)->whereKey($id)->first(),
            'group' => Group::query()->forOrganization($organizationId)->whereKey($id)->first(),
            default => null,
        };

        abort_if($subject === null, 404);

        return $subject;
    }

    private function snapshot(
        ClosureSnapshotQueries $snapshots,
        string $kind,
        string $organizationId,
        string $id,
    ): ClosureSnapshot {
        return match ($kind) {
            'program' => $snapshots->forProgram($organizationId, $id),
            'course' => $snapshots->forCourse($organizationId, $id),
            default => $snapshots->forGroup($organizationId, $id),
        };
    }

    /**
     * صفوف قسم واحد من الأرشيف، أو فراغ إذا لم يملك المستخدم صلاحية نوعه.
     *
     * @param Builder<covariant Model> $query
     * @return list<array<string, mixed>>
     */
    private function closedRows(mixed $query, string $kind, Request $request): array
    {
        $permission = match ($kind) {
            'program' => 'program.manage',
            'course' => 'course.manage',
            default => 'group.view',
        };

        if ($request->user()?->can($permission) !== true) {
            return [];
        }

        return $query
            ->orderByDesc('closed_at')
            ->get()
            ->map(static fn (Model $record): array => [
                'id' => (string) $record->getKey(),
                'kind' => $kind,
                'code' => (string) $record->getAttribute('code'),
                'name' => LocalizedJsonColumn::display($record->getAttribute('name')),
                'closedAt' => $record->getAttribute('closed_at')?->toIso8601String(),
                'closedBy' => $record->getAttribute('closed_by'),
                'reason' => $record->getAttribute('closure_reason'),
                'summary' => $record->getAttribute('closure_summary') ?? [],
            ])
            ->values()
            ->all();
    }

    /**
     * يستبدل معرّف المُقفِل باسمه — السجل بلا اسم فاعل نصفُ سجل.
     *
     * @param array<string, list<array<string, mixed>>> $sections
     * @return array<string, list<array<string, mixed>>>
     */
    private function withActorNames(array $sections, UserQueryService $users): array
    {
        $ids = [];
        foreach ($sections as $rows) {
            foreach ($rows as $row) {
                if (is_string($row['closedBy']) && $row['closedBy'] !== '') {
                    $ids[] = $row['closedBy'];
                }
            }
        }

        $summaries = $ids === [] ? [] : $users->summariesByIds(array_values(array_unique($ids)));

        foreach ($sections as $section => $rows) {
            foreach ($rows as $index => $row) {
                $id = $row['closedBy'];
                $sections[$section][$index]['closedBy'] = is_string($id)
                    ? ($summaries[$id]->name ?? __('console.not_set'))
                    : __('console.not_set');
            }
        }

        return $sections;
    }

    private function organizationId(Request $request): string
    {
        $id = $request->user()?->getAttribute('organization_id');
        abort_unless(is_string($id) && $id !== '', 403);

        return $id;
    }

    /**
     * المرشّحون للإقفال — المفتوح فقط، بلا حساب موانعه هنا.
     *
     * الموانع تُحسب عند المعاينة وحدها: حسابها لكل صف في الصفحة يعني استعلامات
     * عبر ثلاثة موديولات لكل عنصر، وهو ثمن لا يدفعه المستخدم مقابل قائمة أسماء.
     *
     * @param Builder<covariant Model> $query
     * @return list<array<string, mixed>>
     */
    private function openRows(mixed $query, string $kind, Request $request): array
    {
        $permission = match ($kind) {
            'program' => 'program.manage',
            'course' => 'course.manage',
            default => 'group.manage',
        };

        if ($request->user()?->can($permission) !== true) {
            return [];
        }

        return $query
            ->orderBy('code')
            ->get()
            ->map(static fn (Model $record): array => [
                'id' => (string) $record->getKey(),
                'kind' => $kind,
                'code' => (string) $record->getAttribute('code'),
                'name' => LocalizedJsonColumn::display($record->getAttribute('name')),
            ])
            ->values()
            ->all();
    }
}
