<?php

declare(strict_types=1);

namespace Modules\Attendance\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Modules\Attendance\Domain\Events\AttendanceRecorded;
use Modules\Attendance\Domain\Models\Attendance;
use Modules\Attendance\Tests\Concerns\CreatesSessionParticipant;
use Modules\Attendance\Tests\Support\ApiUser;
use Shared\Testing\Fixtures;
use Tests\TestCase;

/**
 * مسارات موديول Attendance:
 *  POST   /api/attendances
 *  GET    /api/attendances
 *  GET    /api/attendances/{attendance}
 *  PATCH  /api/attendances/{attendance}
 *  POST   /api/attendances/{attendance}/confirm
 */
final class AttendanceApiTest extends TestCase
{
    use CreatesSessionParticipant;
    use RefreshDatabase;

    private const ACTOR_ID = '01ACTORATTENDANCE00000000';

    public function test_records_attendance_through_the_api_and_returns_201(): void
    {
        Event::fake([AttendanceRecorded::class]);
        Gate::define('attendance.record', fn (): bool => true);
        Gate::define('attendance.view', fn (): bool => true);

        $participantId = $this->createSessionParticipant();

        $response = $this->actingAs(new ApiUser(self::ACTOR_ID, $this->organizationId))
            ->postJson('/api/attendances', [
                'session_participant_id' => $participantId,
                'attended_minutes' => 55,
                'session_minutes' => 60,
                'joined_after_minutes' => 2,
                'left_before_minutes' => 0,
                'reason' => 'مزامنة حضور الطالب من الفصل الافتراضي',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.session_participant_id', $participantId)
            ->assertJsonPath('data.status', 'present')
            ->assertJsonPath('data.derived_status', 'present');

        Event::assertDispatched(AttendanceRecorded::class);
    }

    public function test_rejects_duplicate_recording_with_validation_error(): void
    {
        Gate::define('attendance.record', fn (): bool => true);

        $participantId = $this->createSessionParticipant();
        Attendance::query()->create([
            'session_participant_id' => $participantId,
            'status' => 'present',
            'derived_status' => 'present',
            'attended_minutes' => 50,
        ]);

        $this->actingAs(new ApiUser(self::ACTOR_ID))
            ->postJson('/api/attendances', [
                'session_participant_id' => $participantId,
                'attended_minutes' => 30,
                'session_minutes' => 60,
            ])->assertUnprocessable()
            ->assertJsonValidationErrors(['session_participant_id']);
    }

    public function test_validates_minutes_bounds(): void
    {
        Gate::define('attendance.record', fn (): bool => true);

        $participantId = $this->createSessionParticipant();

        $this->actingAs(new ApiUser(self::ACTOR_ID, $this->organizationId))
            ->postJson('/api/attendances', [
                'session_participant_id' => $participantId,
                'attended_minutes' => -1,
                'session_minutes' => 60,
            ])->assertUnprocessable()
            ->assertJsonValidationErrors(['attended_minutes']);
    }

    public function test_forbids_recording_without_the_ability(): void
    {
        Gate::define('attendance.record', fn (): bool => false);

        $this->actingAs(new ApiUser(self::ACTOR_ID))
            ->postJson('/api/attendances', [
                'session_participant_id' => (string) str()->ulid(),
                'attended_minutes' => 30,
                'session_minutes' => 60,
            ])->assertForbidden();
    }

    public function test_lists_and_shows_attendances_with_view_ability(): void
    {
        Gate::define('attendance.view', fn (): bool => true);
        Gate::define('attendance.record', fn (): bool => false);

        $participantId = $this->createSessionParticipant();
        $record = Attendance::query()->create([
            'session_participant_id' => $participantId,
            'status' => 'late',
            'derived_status' => 'late',
            'attended_minutes' => 58,
        ]);

        $this->actingAs(new ApiUser(self::ACTOR_ID, $this->organizationId))
            ->getJson('/api/attendances?status=late')
            ->assertOk()
            ->assertJsonPath('data.0.id', (string) $record->getKey());

        $this->actingAs(new ApiUser(self::ACTOR_ID, $this->organizationId))
            ->getJson('/api/attendances/'.$record->getKey())
            ->assertOk()
            ->assertJsonPath('data.status', 'late');
    }

    public function test_overrides_status_with_reason_through_the_api(): void
    {
        config()->set('academic.attendance.thresholds', [
            'partial_min_percent' => 25,
            'present_min_percent' => 75,
            'left_early_before_minutes' => 10,
            'late_after_minutes' => 5,
        ]);
        Gate::define('attendance.override', fn (): bool => true);

        $participantId = $this->createSessionParticipant();
        $actorId = Fixtures::userId();
        $record = Attendance::query()->create([
            'session_participant_id' => $participantId,
            'status' => 'no_show',
            'derived_status' => 'no_show',
        ]);

        $this->actingAs(new ApiUser($actorId, $this->organizationId))
            ->patchJson('/api/attendances/'.$record->getKey(), [
                'status' => 'excused',
                'reason' => 'عذر طبي موثق بمستشفى معتمد',
            ])->assertOk()
            ->assertJsonPath('data.status', 'excused')
            ->assertJsonPath('data.derived_status', 'no_show');

        $record->refresh();
        expect($record->override_reason)->toBe('عذر طبي موثق بمستشفى معتمد');
    }

    public function test_rejects_override_without_reason(): void
    {
        Gate::define('attendance.override', fn (): bool => true);

        $participantId = $this->createSessionParticipant();
        $record = Attendance::query()->create([
            'session_participant_id' => $participantId,
            'status' => 'no_show',
            'derived_status' => 'no_show',
        ]);

        $this->actingAs(new ApiUser(self::ACTOR_ID, $this->organizationId))
            ->patchJson('/api/attendances/'.$record->getKey(), [
                'status' => 'excused',
            ])->assertUnprocessable()
            ->assertJsonValidationErrors(['reason']);
    }

    public function test_confirms_attendance_through_the_api(): void
    {
        Gate::define('attendance.record', fn (): bool => true);

        $participantId = $this->createSessionParticipant();
        $actorId = Fixtures::userId();
        $record = Attendance::query()->create([
            'session_participant_id' => $participantId,
            'status' => 'present',
            'derived_status' => 'present',
        ]);

        $this->actingAs(new ApiUser($actorId, $this->organizationId))
            ->postJson('/api/attendances/'.$record->getKey().'/confirm', [
                'reason' => 'اعتماد سجل الحضور بعد مراجعة المعلم',
            ])
            ->assertOk()
            ->assertJsonPath('data.is_confirmed', true)
            ->assertJsonPath('data.confirmed_by', $actorId);
    }

    public function test_requires_authentication_for_all_routes(): void
    {
        $participantId = $this->createSessionParticipant();
        $record = Attendance::query()->create([
            'session_participant_id' => $participantId,
            'status' => 'present',
            'derived_status' => 'present',
        ]);

        $this->postJson('/api/attendances', [])->assertUnauthorized();
        $this->getJson('/api/attendances')->assertUnauthorized();
        $this->getJson('/api/attendances/'.$record->getKey())->assertUnauthorized();
        $this->patchJson('/api/attendances/'.$record->getKey(), [])->assertUnauthorized();
        $this->postJson('/api/attendances/'.$record->getKey().'/confirm')->assertUnauthorized();
    }
}
