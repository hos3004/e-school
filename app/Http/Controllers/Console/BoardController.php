<?php

declare(strict_types=1);

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Console\Support\ConsoleContext;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\AcademicReports\Domain\Contracts\SessionReportStatusQueries;
use Modules\Integrations\Domain\Contracts\GreenApiConnections;
use Modules\Payroll\Domain\Contracts\TeacherDuesQueries;
use Modules\Sessions\Domain\Contracts\SessionAdministrationQueries;
use Modules\Sessions\Domain\Contracts\SessionClosureBacklogQuery;
use Modules\Sessions\Domain\ValueObjects\SessionClosureBacklog;
use Modules\Staff\Domain\Contracts\StaffQueries;
use Modules\VirtualClassroom\Domain\Contracts\ClassroomStartedFlagQuery;

/**
 * اللوحة — الشاشة التي يفتحها المالك صباحًا ليعرف هل يحتاجه شيء.
 *
 * ليست تقريرًا. كل بطاقة فيها تُنتج فعلًا واحدًا محددًا اليوم، وما لا يُنتج فعلًا
 * لا يُعرض. وبطاقة صفرها يعني «بخير» تختفي من الشاشة بدل أن تضيء أخضر، لأن
 * شاشة مليئة بالأخضر تُقرأ بسرعة ثم يُكفّ عن قراءتها.
 *
 * قاعدتان تحكمان كل رقم هنا:
 *
 * **١. لكل رقم مصدر واحد.** ستة قياسات مستقلة لسؤال «كم حصة بلا إقفال» أعطت
 * أربعة أرقام مختلفة، والفرق اختلاف تعريف لا تحرّك بيانات. فرقم الطابور يأتي
 * من `SessionClosureBacklogQuery` وحده، ومقام بطاقة الدفتر هو ذلك الرقم نفسه
 * لا استعلام ثانٍ.
 *
 * **٢. المبلغ لا يخرج بلا مقامه.** «575 جنيهًا مستحقة» صحيح حسابيًا وكاذب
 * واقعيًا ما دامت عشرات الحصص لم تُقفل فلم تُنتج قيدة أصلًا. فالمبلغ في هذه
 * اللوحة مقترن دائمًا بعدد الحصص التي لم تدخل الدفتر بعد؛ وإن تعذّر حساب
 * المقام لا يُعرض المبلغ إطلاقًا.
 *
 * وما لا يقيسه النظام تقوله اللوحة صراحة بدل أن تصمت: لا إيراد ولا ربح هنا،
 * لأن الفوترة غير مُدارة في هذه المنصة.
 */
final class BoardController extends Controller
{
    public function __invoke(
        Request $request,
        SessionClosureBacklogQuery $closure,
        ClassroomStartedFlagQuery $rooms,
        SessionAdministrationQueries $sessions,
        SessionReportStatusQueries $reports,
        TeacherDuesQueries $dues,
        StaffQueries $staff,
        GreenApiConnections $whatsapp,
        ConsoleContext $context,
    ): Response {
        $user = $request->user();
        $organizationId = (string) data_get($user, 'organization_id');
        abort_if($organizationId === '', 403);

        $context = $context->forRequest($request);
        $viewerTimezone = (string) $context['timezone'];

        /*
         * «اليوم» بتوقيت المدرسة لا بتوقيت المشاهد: مديران يفتحان اللوحة في
         * اللحظة نفسها من منطقتين مختلفتين يجب أن يقرآ العدد نفسه، وإلا عاد
         * الرقمان المتضاربان لسؤال واحد — وهو ما بُنيت هذه اللوحة لإنهائه.
         */
        $schoolTimezone = (string) ($context['school']['timezone'] ?? $viewerTimezone);
        $schoolNow = CarbonImmutable::now('UTC')->setTimezone($schoolTimezone);

        // لحظة قطع واحدة لكل قياسات الطابور في هذا الطلب.
        $cutoff = $closure->cutoff();
        $backlog = $closure->backlog($organizationId, $cutoff);
        $awaiting = $closure->awaitingReview($organizationId);

        return Inertia::render('Console/Board', [
            'today' => [
                'count' => $closure->countActiveBetween(
                    $organizationId,
                    $schoolNow->startOfDay()->utc(),
                    $schoolNow->endOfDay()->utc(),
                ),
            ],
            'whatsappEnabled' => $whatsapp->isChannelEnabled($organizationId),
            'backlog' => $this->backlogCard($backlog, $closure, $rooms, $staff, $organizationId, $cutoff),
            'awaiting' => $this->awaitingCard($awaiting, $reports, $closure, $organizationId),
            'ledger' => $this->ledgerCard($user, $dues, $closure, $staff, $organizationId),
            'canReview' => (bool) $user?->can('session.finalize'),
            'reviewUrl' => route('console.sessions.review'),
            'duesUrl' => route('console.teacher-dues.index'),
            'whatsappUrl' => route('console.whatsapp.index'),
            'liveUrl' => route('console.live'),
            'timezone' => $viewerTimezone,
        ]);
    }

    /**
     * التقسيم بين «فُتحت غرفتها» و«لم تُفتح» هو ما يحوّل رقمًا إلى فعلين مختلفين:
     * الأولى عمل تمّ ينتظر اعتمادًا، والثانية سؤال للمعلمة عمّا جرى.
     *
     * @return array<string, mixed>
     */
    private function backlogCard(
        SessionClosureBacklog $backlog,
        SessionClosureBacklogQuery $closure,
        ClassroomStartedFlagQuery $rooms,
        StaffQueries $staff,
        string $organizationId,
        CarbonImmutable $cutoff,
    ): array {
        if ($backlog->total === 0) {
            return ['total' => 0, 'teachers' => [], 'started' => 0, 'neverStarted' => 0,
                'olderThanAWeek' => 0, 'oldest' => null];
        }

        $flags = $rooms->forSessionIds($closure->backlogSessionIds($organizationId, $cutoff));
        $started = count(array_filter($flags));

        $names = $staff->namesForProfiles($organizationId, array_keys($backlog->byTeacher));
        $teachers = [];
        foreach ($backlog->byTeacher as $staffProfileId => $total) {
            $teachers[] = [
                'id' => (string) $staffProfileId,
                'name' => $names[$staffProfileId] ?? __('console.unassigned'),
                'total' => $total,
            ];
        }
        usort($teachers, static fn (array $a, array $b): int => $b['total'] <=> $a['total']);

        return [
            'total' => $backlog->total,
            'teachers' => $teachers,
            'started' => $started,
            'neverStarted' => $backlog->total - $started,
            'olderThanAWeek' => $backlog->olderThanAWeek,
            'oldest' => $backlog->oldestScheduledEnd,
        ];
    }

    /** @return array<string, mixed> */
    private function awaitingCard(
        SessionClosureBacklog $awaiting,
        SessionReportStatusQueries $reports,
        SessionClosureBacklogQuery $closure,
        string $organizationId,
    ): array {
        if ($awaiting->total === 0) {
            return ['total' => 0, 'missingReport' => 0, 'oldest' => null];
        }

        /*
         * الاعتماد الآلي يشترط تقرير الحصة، فما لا تقرير له لن يُقفل آليًا
         * أبدًا مهما طال — وهو الجزء الذي يحتاج يد المدير حقًا.
         */
        /*
         * `forSessions` تعيد الحصص التي **لها** صف تقرير فقط؛ الحصة بلا صف
         * غائبة عن الخريطة لا موجودة بقيمة فارغة. فالمرور على المُعاد وحده
         * كان يعدّ صفرًا دائمًا في اللحظة التي تكون كلها فيها بلا تقرير.
         */
        $ids = $closure->awaitingReviewSessionIds($organizationId);
        $states = $reports->forSessions($ids);
        $missing = 0;
        foreach ($ids as $id) {
            $missing += (int) (((($states[$id] ?? null)?->state()) ?? 'missing') === 'missing');
        }

        return [
            'total' => $awaiting->total,
            'missingReport' => $missing,
            'oldest' => $awaiting->oldestScheduledEnd,
        ];
    }

    /**
     * المبلغ ومقامه في مخرَج واحد: أي عرض للمبلغ بلا عدد الحصص التي لم تدخل
     * الدفتر بعد يوهم المدير باكتمال ليس موجودًا.
     *
     * والمقام يُقاس على **كل** حصة مضت في الفترة بلا قيدة، لا على الطابور
     * وحده: الحصة المنتهية بحالة نهائية (غياب، إلغاء، تأجيل) قد تحمل أثرًا
     * ماليًا ولا تعود إلى الطابور أبدًا. قياسه بالطابور كان سيبلغ صفرًا يوم
     * تُصفّى الحصص المعلّقة بينما مالٌ حقيقي خارج الدفتر — وهي بالضبط الطمأنة
     * الكاذبة التي وُجدت هذه البطاقة لمنعها.
     *
     * والبسط والمقام على نافذة واحدة: فترة الرواتب نفسها، فلا يُقارن عدد قيود
     * شهر بفجوة بلا حدّ زمني.
     *
     * @return array<string, mixed>|null
     */
    private function ledgerCard(
        mixed $user,
        TeacherDuesQueries $dues,
        SessionClosureBacklogQuery $closure,
        StaffQueries $staff,
        string $organizationId,
    ): ?array {
        if (!(bool) config('features.payroll') || $user === null || !$user->can('payroll.view')) {
            return null;
        }

        $periods = $dues->periods($user);
        $current = $periods[0] ?? null;

        if ($current === null) {
            return null;
        }

        $from = CarbonImmutable::parse((string) $current['startsOn'], 'UTC')->startOfDay();
        $until = CarbonImmutable::parse((string) $current['endsOn'], 'UTC')->addDay()->startOfDay();

        $pastIds = $closure->pastSessionIdsInWindow($organizationId, $from, $until);
        $withEntries = $dues->sessionIdsWithEntries($organizationId, $pastIds);
        $withoutEntry = count($pastIds) - count($withEntries);

        $statement = $dues->statement($user, (string) $current['id']);
        $names = $staff->namesForProfiles(
            $organizationId,
            array_map(static fn (array $row): string => (string) $row['staffProfileId'], $statement->teachers),
        );

        $teachers = [];
        foreach ($statement->teachers as $row) {
            foreach ($row['totals'] as $total) {
                $teachers[] = [
                    'key' => $row['staffProfileId'].'-'.$total['currency'],
                    'name' => $names[$row['staffProfileId']] ?? __('console_dues.archived_teacher'),
                    'amount' => $total['amounts']['net'],
                    'minorUnits' => (int) $total['minorUnits']['net'],
                    'currency' => $total['currency'],
                ];
            }
        }

        // ترتيب بالوحدات الصغرى لا بالنص: "75.00" أكبر من "475.00" حرفيًا.
        usort($teachers, static fn (array $a, array $b): int => $b['minorUnits'] <=> $a['minorUnits']);

        if (count($statement->entries) === 0 && $withoutEntry === 0) {
            // لا قيود ولا فجوة: لا شيء تقوله البطاقة، فلا تُعرض.
            return null;
        }

        return [
            'entries' => count($statement->entries),
            'teachers' => $teachers,
            'sessionsWithoutEntry' => $withoutEntry,
            'period' => [
                'label' => $current['year'].'/'.str_pad((string) $current['month'], 2, '0', STR_PAD_LEFT),
                'status' => $current['status'],
                'neverCalculated' => $current['approvedAt'] === null && $current['paidAt'] === null,
            ],
        ];
    }
}
