<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Sessions\Application\Actions\DecideTeacherApologyAction;
use Modules\Sessions\Application\Actions\SubmitTeacherApologyAction;
use Modules\Sessions\Domain\Enums\ApologyStatus;
use Modules\Sessions\Domain\Enums\SessionStatus;
use Modules\Sessions\Domain\Models\Session;
use Modules\Sessions\Domain\Services\ApologyEscalationEvaluator;
use Shared\Support\BusinessRuleViolation;
use Shared\Testing\Fixtures;

/**
 * قاعدتان من العميل تُختبران هنا لأن كسرهما صامت ومكلف:
 *
 *  1. اعتذار المعلم **لا يُلغي الحصة** مهما كان (client-answers §ي).
 *  2. عدّ الاعتذارات على نافذة **متحركة** لا شهر ميلادي (client-answers §ك)،
 *     ولا عقوبة آلية على المعلم مهما تكرر.
 */
/**
 * كورس صالح للاختبار. لا يوجد في Fixtures المشتركة مساعد للكورسات،
 * وإضافته هناك تخص موديولًا آخر — فننشئه محليًا بالجدول مباشرة.
 */
function apologyCourseId(): string
{
    static $id = null;

    if ($id !== null && DB::table('courses')->where('id', $id)->exists()) {
        return $id;
    }

    $orgId = Fixtures::organizationId();

    $existing = DB::table('courses')->where('organization_id', $orgId)->value('id');

    if (is_string($existing) && $existing !== '') {
        return $id = $existing;
    }

    $programId = (string) Str::ulid();
    DB::table('programs')->insert([
        'id' => $programId,
        'organization_id' => $orgId,
        'code' => 'PRG-'.strtoupper(substr($programId, -6)),
        'name' => json_encode(['ar' => 'برنامج اختبار', 'en' => 'Test program'], JSON_UNESCAPED_UNICODE),
        'default_session_minutes' => 60,
        'currency' => 'EGP',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $levelId = (string) Str::ulid();
    DB::table('levels')->insert([
        'id' => $levelId,
        'program_id' => $programId,
        'code' => 'LVL-'.strtoupper(substr($levelId, -6)),
        'name' => json_encode(['ar' => 'مستوى', 'en' => 'Level'], JSON_UNESCAPED_UNICODE),
        'sort_order' => 1,
        'created_at' => now(),
    ]);

    $courseId = (string) Str::ulid();
    DB::table('courses')->insert([
        'id' => $courseId,
        'organization_id' => $orgId,
        'level_id' => $levelId,
        'code' => 'CRS-'.strtoupper(substr($courseId, -6)),
        'name' => json_encode(['ar' => 'كورس اختبار', 'en' => 'Test course'], JSON_UNESCAPED_UNICODE),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $id = $courseId;
}

function apologySession(array $overrides = []): Session
{
    $session = new Session;
    $session->fill(array_merge([
        'organization_id' => Fixtures::organizationId(),
        'course_id' => apologyCourseId(),
        'staff_profile_id' => Fixtures::staffProfileId(),
        'session_type' => 'individual',
        'status' => SessionStatus::Scheduled->value,
        'scheduled_start' => CarbonImmutable::now('UTC')->addDays(2),
        'scheduled_end' => CarbonImmutable::now('UTC')->addDays(2)->addHour(),
        'title' => ['ar' => 'حصة اختبار', 'en' => 'Test session'],
    ], $overrides));
    $session->save();

    return $session->refresh();
}

it('يقبل الاعتذار بسبب مكتوب ولا يمس الحصة عند التقديم', function (): void {
    $session = apologySession();

    $apology = app(SubmitTeacherApologyAction::class)->execute(
        (string) $session->id,
        (string) $session->staff_profile_id,
        'ظرف عائلي طارئ',
    );

    expect($apology->status)->toBe(ApologyStatus::Submitted)
        ->and($apology->reason)->toBe('ظرف عائلي طارئ');

    // الحصة لم تتغيّر بأي شكل عند مجرد التقديم.
    expect($session->refresh()->status)->toBe(SessionStatus::Scheduled)
        ->and($session->staff_profile_id)->not->toBeNull();
});

it('يرفض الاعتذار بلا سبب مكتوب', function (): void {
    $session = apologySession();

    expect(fn () => app(SubmitTeacherApologyAction::class)->execute(
        (string) $session->id,
        (string) $session->staff_profile_id,
        '   ',
    ))->toThrow(BusinessRuleViolation::class);
});

it('لا يُلغي الحصة عند اعتماد الاعتذار — القاعدة الحاكمة', function (): void {
    $session = apologySession(['status' => SessionStatus::Confirmed->value]);

    $apology = app(SubmitTeacherApologyAction::class)->execute(
        (string) $session->id,
        (string) $session->staff_profile_id,
        'مرض مفاجئ',
    );

    app(DecideTeacherApologyAction::class)->approve(
        (string) $apology->id,
        Fixtures::userId(),
    );

    $session->refresh();

    // هذا هو بيت القصيد: الحصة قائمة، والمعلم ما زال مسندًا حتى يُختار بديل.
    expect($session->status)->toBe(SessionStatus::Confirmed)
        ->and($session->status)->not->toBe(SessionStatus::CancelledByTeacher)
        ->and($session->status)->not->toBe(SessionStatus::CancelledBySchool);

    expect($apology->refresh()->status)->toBe(ApologyStatus::Approved)
        ->and($apology->status->awaitsSubstitute())->toBeTrue();
});

it('يمنع رفض الاعتذار بلا سبب مكتوب', function (): void {
    $session = apologySession();

    $apology = app(SubmitTeacherApologyAction::class)->execute(
        (string) $session->id,
        (string) $session->staff_profile_id,
        'سبب ما',
    );

    expect(fn () => app(DecideTeacherApologyAction::class)->reject(
        (string) $apology->id,
        Fixtures::userId(),
        '',
    ))->toThrow(BusinessRuleViolation::class);
});

it('يعدّ الاعتذارات على نافذة متحركة لا على شهر ميلادي', function (): void {
    config()->set('discipline.teacher.counter_window_days', 30);

    $staffId = Fixtures::staffProfileId();
    $now = CarbonImmutable::parse('2026-08-22 12:00:00', 'UTC');

    // اعتذار عمره 10 أيام — داخل النافذة.
    seedDecidedApology($staffId, $now->subDays(10));
    // اعتذار عمره 31 يومًا — خارجها. لو كان العدّ شهريًا لدخل أحيانًا.
    seedDecidedApology($staffId, $now->subDays(31));

    $evaluator = app(ApologyEscalationEvaluator::class);

    expect($evaluator->countInWindow($staffId, $now))->toBe(1);

    // الاعتذار القادم هو الثاني في النافذة → تحذير لا عقوبة.
    $verdict = $evaluator->evaluate($staffId, $now);

    expect($verdict['occurrence'])->toBe(2)
        ->and($verdict['window_days'])->toBe(30)
        ->and($verdict['action'])->toBe('warning')
        ->and($verdict['creates_escalation'])->toBeFalse();
});

it('يصعّد عند الاعتذار الثالث دون أي عقوبة آلية على المعلم', function (): void {
    config()->set('discipline.teacher.counter_window_days', 30);

    $staffId = Fixtures::staffProfileId();
    $now = CarbonImmutable::parse('2026-08-22 12:00:00', 'UTC');

    seedDecidedApology($staffId, $now->subDays(5));
    seedDecidedApology($staffId, $now->subDays(12));

    $verdict = app(ApologyEscalationEvaluator::class)->evaluate($staffId, $now);

    expect($verdict['occurrence'])->toBe(3)
        ->and($verdict['action'])->toBe('escalation')
        ->and($verdict['creates_escalation'])->toBeTrue();

    // ولا يوجد في الإعدادات أي إجراء آلي يمسّ المعلم — قفل صريح.
    expect(config('discipline.teacher.never_automatic.suspend'))->toBeFalse()
        ->and(config('discipline.teacher.never_automatic.terminate'))->toBeFalse()
        ->and(config('discipline.teacher.never_automatic.change_status'))->toBeFalse();
});

it('لا يحتسب الاعتذار المرفوض في السُلَّم', function (): void {
    config()->set('discipline.teacher.counter_window_days', 30);

    $staffId = Fixtures::staffProfileId();
    $now = CarbonImmutable::parse('2026-08-22 12:00:00', 'UTC');

    seedDecidedApology($staffId, $now->subDays(3), ApologyStatus::Rejected);

    expect(app(ApologyEscalationEvaluator::class)->countInWindow($staffId, $now))->toBe(0);
});

/**
 * اعتذار مبتوت مباشرة في القاعدة — نتجاوز الـAction عمدًا لأن الغرض
 * اختبار العدّاد الزمني لا مسار التقديم.
 */
function seedDecidedApology(
    string $staffProfileId,
    CarbonImmutable $decidedAt,
    ApologyStatus $status = ApologyStatus::Approved,
): void {
    $session = apologySession([
        'staff_profile_id' => $staffProfileId,
        'scheduled_start' => $decidedAt->addHours(2),
        'scheduled_end' => $decidedAt->addHours(3),
    ]);

    DB::table('teacher_apologies')->insert([
        'id' => (string) Str::ulid(),
        'organization_id' => (string) $session->organization_id,
        'session_id' => (string) $session->id,
        'staff_profile_id' => $staffProfileId,
        'status' => $status->value,
        'reason' => 'اعتذار سابق',
        'submitted_at' => $decidedAt->subHour(),
        'is_late_notice' => false,
        'decided_by' => Fixtures::userId(),
        'decided_at' => $decidedAt,
        'created_at' => $decidedAt,
        'updated_at' => $decidedAt,
    ]);
}
