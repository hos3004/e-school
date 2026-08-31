<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Modules\Attendance\Application\Actions\ConfirmAttendanceAction;
use Modules\Attendance\Application\Actions\OverrideAttendanceAction;
use Modules\Attendance\Application\Actions\RecordAttendanceAction;
use Modules\Attendance\Domain\Enums\AttendanceStatus;
use Modules\Attendance\Presentation\Filament\Resources\AttendanceFilamentResource;
use Modules\Attendance\Tests\Concerns\CreatesSessionParticipant;
use Modules\Audit\Domain\Models\AuditLog;
use Modules\Identity\Domain\Models\User;
use Modules\Organization\Domain\Models\Organization;
use Modules\Sessions\Domain\Models\Session;
use Modules\Sessions\Presentation\Filament\Resources\SessionResource;
use Shared\Support\BusinessRuleViolation;

uses(RefreshDatabase::class, CreatesSessionParticipant::class);

it('renders a tenant isolated attendance hub with real operational context', function (): void {
    Gate::before(static fn (): bool => true);
    Filament::setCurrentPanel('admin');
    $participantId = $this->createSessionParticipant();
    $operator = User::query()->where('organization_id', $this->organizationId)->firstOrFail();
    $attendance = app(RecordAttendanceAction::class)->execute(
        sessionParticipantId: $participantId,
        attendedMinutes: 55,
        sessionMinutes: 60,
        joinedAfterMinutes: 2,
        organizationId: $this->organizationId,
        actorId: (string) $operator->id,
        reason: 'مزامنة بيانات الفصل الافتراضي',
    );
    app(ConfirmAttendanceAction::class)->execute(
        $attendance,
        (string) $operator->id,
        'مراجعة المعلم لبيانات الدخول والخروج',
        $this->organizationId,
    );
    app(OverrideAttendanceAction::class)->execute(
        $attendance,
        AttendanceStatus::Late,
        'ثبت تأخر الطالب بعد مراجعة توقيت دخوله',
        (string) $operator->id,
        $this->organizationId,
    );

    $this->actingAs($operator)
        ->get(AttendanceFilamentResource::getUrl('index', panel: 'admin'))
        ->assertOk()
        ->assertSeeText('طالب تجريبي')
        ->assertSeeText('حصة اختبار')
        ->assertSeeText('مادة اختبار')
        ->assertDontSee($participantId);

    $this->get(AttendanceFilamentResource::getUrl('view', ['record' => $attendance], panel: 'admin'))
        ->assertOk()
        ->assertSeeText('طالب تجريبي')
        ->assertSeeText('معلم تجريبي')
        ->assertSeeText(__('attendance::filament.hub.audit'))
        ->assertSeeText('ثبت تأخر الطالب بعد مراجعة توقيت دخوله');

    /** @var Session $session */
    $session = Session::query()->whereKey((string) DB::table('session_participants')->where('id', $participantId)->value('session_id'))->firstOrFail();
    $this->get(SessionResource::getUrl('view', ['record' => $session], panel: 'admin'))
        ->assertOk()
        ->assertSeeText(AttendanceStatus::Late->label())
        ->assertSeeText(__('sessions::fields.derived_attendance_status'));

    $otherOrganization = Organization::factory()->create();
    $otherOperator = User::factory()->inOrganization((string) $otherOrganization->id)->create();
    $this->actingAs($otherOperator);
    expect(AttendanceFilamentResource::getEloquentQuery()->whereKey($attendance->id)->exists())->toBeFalse();
});

it('audits the full attendance decision flow and rejects cross tenant or revoked participants', function (): void {
    $participantId = $this->createSessionParticipant();
    $operatorId = (string) User::query()->where('organization_id', $this->organizationId)->value('id');
    $attendance = app(RecordAttendanceAction::class)->execute(
        $participantId,
        60,
        60,
        organizationId: $this->organizationId,
        actorId: $operatorId,
        reason: 'رصد من الفصل الافتراضي',
    );
    app(ConfirmAttendanceAction::class)->execute(
        $attendance,
        $operatorId,
        'اعتماد المعلم بعد المراجعة',
        $this->organizationId,
    );
    app(OverrideAttendanceAction::class)->execute(
        $attendance,
        AttendanceStatus::Excused,
        'عذر طبي موثق بعد الاعتماد الأولي',
        $operatorId,
        $this->organizationId,
    );

    expect(AuditLog::query()
        ->where('organization_id', $this->organizationId)
        ->where('auditable_type', 'attendances')
        ->where('auditable_id', $attendance->id)
        ->pluck('action')
        ->all())->toEqualCanonicalizing([
            'attendance.recorded',
            'attendance.confirmed',
            'attendance.overridden',
        ]);

    $otherOrganization = Organization::factory()->create();
    expect(fn () => app(RecordAttendanceAction::class)->execute(
        $participantId,
        50,
        60,
        organizationId: (string) $otherOrganization->id,
    ))->toThrow(BusinessRuleViolation::class);

    DB::table('session_participants')->where('id', $participantId)->update([
        'revoked_at' => now('UTC'),
        'revocation_reason' => 'سحب الدعوة للاختبار',
    ]);
    expect(fn () => app(ConfirmAttendanceAction::class)->execute(
        $attendance->forceFill(['confirmed_at' => null]),
        $operatorId,
        'محاولة اعتماد مشاركة مسحوبة',
        $this->organizationId,
    ))->toThrow(BusinessRuleViolation::class);
});

it('rolls the attendance lifecycle migration down and reapplies it cleanly', function (): void {
    $migration = require base_path('modules/Attendance/database/migrations/2026_08_24_210000_harden_attendance_lifecycle.php');

    $migration->down();
    expect(Schema::hasColumn('attendances', 'deleted_at'))->toBeFalse();

    $migration->up();
    expect(Schema::hasColumn('attendances', 'deleted_at'))->toBeTrue();
});
