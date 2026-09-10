<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Modules\Attendance\Tests\Concerns\CreatesSessionParticipant;
use Modules\VirtualClassroom\Domain\Contracts\VirtualClassroomProvider;

uses(RefreshDatabase::class, CreatesSessionParticipant::class);

/**
 * رابط دخول الطالب اليدوي: المعلم ينسخه ويرسله للطالب المتعذّر دخوله لحسابه.
 *
 * ما يثبته هذا الملف: التوقيع إلزامي، والرابط يحمل هوية الطالب فيظل الحضور
 * منسوبًا له، ونافذة الدخول والتجميد وسحب الدعوة تُفرض على المسار العام،
 * ولا يصلح توقيع حصة لحصة أخرى.
 */
function studentLinkContext(string $participantId): array
{
    $participant = DB::table('session_participants')->where('id', $participantId)->first();
    $student = DB::table('student_profiles')->where('id', $participant->student_profile_id)->first();

    $start = CarbonImmutable::now('UTC')->addMinutes(5);
    DB::table('sessions')->where('id', $participant->session_id)->update([
        'status' => 'scheduled',
        'scheduled_start' => $start,
        'scheduled_end' => $start->addHour(),
    ]);

    return [
        'participant_id' => $participantId,
        'session_id' => (string) $participant->session_id,
        'enrollment_id' => (string) $participant->enrollment_id,
        'student_profile_id' => (string) $participant->student_profile_id,
        'student_user_id' => (string) $student->user_id,
        'start' => $start,
        'end' => $start->addHour(),
    ];
}

function studentLinkUrl(array $context, ?CarbonImmutable $expiresAt = null): string
{
    return URL::temporarySignedRoute(
        'classroom.student-link',
        $expiresAt ?? $context['end']->addMinutes(15),
        ['session' => $context['session_id'], 'participant' => $context['participant_id']],
    );
}

beforeEach(function (): void {
    $this->withoutVite();
    config(['virtual-classroom.default' => 'null']);
    app()->forgetInstance(VirtualClassroomProvider::class);
});

it('sends the student to the classroom under their own identity', function (): void {
    $context = studentLinkContext($this->createSessionParticipant()); // @phpstan-ignore method.notFound

    $response = $this->get(studentLinkUrl($context));

    $response->assertRedirect();
    $target = (string) $response->headers->get('Location');

    $classroom = DB::table('classrooms')->where('session_id', $context['session_id'])->first();
    expect($classroom)->not->toBeNull()
        ->and($classroom->status)->toBe('provisioned');

    // المزوّد الوهمي يوقّع الرابط بهوية الداخل؛ تطابقها يثبت أن الحضور سيُنسب للطالب.
    $expected = hash('sha256', implode('|', [
        (string) $classroom->external_id,
        'طالب تجريبي',
        'viewer',
        $context['student_user_id'],
    ]));
    expect($target)->toContain($expected);

    expect(DB::table('audit_log')
        ->where('action', 'classroom.student_link.used')
        ->where('auditable_id', $context['participant_id'])
        ->where('actor_id', $context['student_user_id'])
        ->count())->toBe(1);
});

it('rejects an unsigned or tampered link', function (): void {
    $context = studentLinkContext($this->createSessionParticipant()); // @phpstan-ignore method.notFound

    $this->get('/classroom/student-link/'.$context['session_id'].'/'.$context['participant_id'])
        ->assertForbidden();

    $this->get(studentLinkUrl($context).'&extra=1')->assertForbidden();
});

it('refuses a signature minted for a different participant of another session', function (): void {
    $first = studentLinkContext($this->createSessionParticipant()); // @phpstan-ignore method.notFound
    $second = studentLinkContext($this->createSessionParticipant()); // @phpstan-ignore method.notFound

    $signed = studentLinkUrl($first);
    $crossed = str_replace($first['participant_id'], $second['participant_id'], $signed);

    $this->get($crossed)->assertForbidden();
});

it('refuses a participant that does not belong to the signed session', function (): void {
    $first = studentLinkContext($this->createSessionParticipant()); // @phpstan-ignore method.notFound
    $second = studentLinkContext($this->createSessionParticipant()); // @phpstan-ignore method.notFound

    $url = URL::temporarySignedRoute(
        'classroom.student-link',
        $first['end']->addMinutes(15),
        ['session' => $first['session_id'], 'participant' => $second['participant_id']],
    );

    $this->get($url)->assertNotFound();
});

it('closes the link outside the join window', function (): void {
    $context = studentLinkContext($this->createSessionParticipant()); // @phpstan-ignore method.notFound
    $url = studentLinkUrl($context);

    $this->travelTo($context['start']->subMinutes(30));
    $this->get($url)->assertStatus(422);

    $this->travelBack();
});

it('blocks a frozen enrollment', function (): void {
    $context = studentLinkContext($this->createSessionParticipant()); // @phpstan-ignore method.notFound
    DB::table('enrollments')->where('id', $context['enrollment_id'])->update([
        'frozen_at' => CarbonImmutable::now('UTC'),
        'status' => 'frozen',
    ]);

    $this->get(studentLinkUrl($context))->assertStatus(422);
});

it('stops working once the invitation is revoked', function (): void {
    $context = studentLinkContext($this->createSessionParticipant()); // @phpstan-ignore method.notFound
    DB::table('session_participants')->where('id', $context['participant_id'])->update([
        'revoked_at' => CarbonImmutable::now('UTC'),
    ]);

    $this->get(studentLinkUrl($context))->assertNotFound();
});

it('expires with the lesson', function (): void {
    $context = studentLinkContext($this->createSessionParticipant()); // @phpstan-ignore method.notFound
    $url = studentLinkUrl($context);

    $this->travelTo($context['end']->addMinutes(16));
    $this->get($url)->assertForbidden();

    $this->travelBack();
});

it('honours the kill switch for links that were already copied', function (): void {
    $context = studentLinkContext($this->createSessionParticipant()); // @phpstan-ignore method.notFound
    $url = studentLinkUrl($context);

    config(['virtual-classroom.student_link.enabled' => false]);

    $this->get($url)->assertNotFound();
});
