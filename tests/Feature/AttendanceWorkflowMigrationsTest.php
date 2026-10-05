<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\Attendance\Tests\Concerns\CreatesSessionParticipant;

uses(RefreshDatabase::class, CreatesSessionParticipant::class);

it('rolls the attendance workflow migrations down and reapplies them cleanly', function (): void {
    $participantId = $this->createSessionParticipant(); // @phpstan-ignore method.notFound
    $participant = DB::table('session_participants')->where('id', $participantId)->sole();
    $session = DB::table('sessions')->where('id', $participant->session_id)->sole();
    $now = now('UTC');

    DB::table('session_participants')
        ->where('id', $participantId)
        ->update(['attended_minutes' => 7, 'attended_seconds' => 420]);

    $apologyId = (string) Str::ulid();
    DB::table('teacher_apologies')->insert([
        'id' => $apologyId,
        'organization_id' => $session->organization_id,
        'session_id' => $session->id,
        'staff_profile_id' => $session->staff_profile_id,
        'status' => 'submitted',
        'reason' => 'Existing teacher apology',
        'submitted_at' => $now,
        'is_late_notice' => false,
        'notice_minutes' => 120,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $violationId = (string) Str::ulid();
    DB::table('violation_events')->insert([
        'id' => $violationId,
        'organization_id' => $session->organization_id,
        'enrollment_id' => $participant->enrollment_id,
        'student_profile_id' => $participant->student_profile_id,
        'session_id' => $session->id,
        'type' => 'unexcused_absence',
        'occurred_at' => $now,
        'window_key' => 'R30',
        'is_countable' => true,
        'created_at' => $now,
    ]);

    /** @var Migration $discipline */
    $discipline = require base_path(
        'modules/Discipline/database/migrations/2026_09_06_030000_add_source_event_id_to_violation_events.php',
    );
    /** @var Migration $liveAttendance */
    $liveAttendance = require base_path(
        'modules/Sessions/database/migrations/2026_09_06_031000_add_live_attendance_state_to_session_participants.php',
    );
    /** @var Migration $studentExcuse */
    $studentExcuse = require base_path(
        'modules/Sessions/database/migrations/2026_09_06_032000_add_excuse_state_to_session_participants.php',
    );
    /** @var Migration $substituteSearch */
    $substituteSearch = require base_path(
        'modules/Sessions/database/migrations/2026_09_06_033000_add_substitute_search_state_to_teacher_apologies.php',
    );

    $substituteSearch->down();
    $studentExcuse->down();
    $liveAttendance->down();
    $discipline->down();

    expect(Schema::hasColumn('violation_events', 'source_event_id'))->toBeFalse()
        ->and(Schema::hasColumn('session_participants', 'current_joined_at'))->toBeFalse()
        ->and(Schema::hasColumn('session_participants', 'attended_seconds'))->toBeFalse()
        ->and(Schema::hasColumn('session_participants', 'excused_at'))->toBeFalse()
        ->and(Schema::hasColumn('session_participants', 'excused_by'))->toBeFalse()
        ->and(Schema::hasColumn('session_participants', 'excuse_reason'))->toBeFalse()
        ->and(Schema::hasColumn('teacher_apologies', 'substitute_search_started_at'))->toBeFalse()
        ->and(Schema::hasColumn('teacher_apologies', 'last_substitute_search_at'))->toBeFalse()
        ->and(Schema::hasColumn('teacher_apologies', 'substitute_candidate_ids'))->toBeFalse()
        ->and(Schema::hasColumn('teacher_apologies', 'substitute_candidate_count'))->toBeFalse();

    $discipline->up();
    $liveAttendance->up();
    $studentExcuse->up();
    $substituteSearch->up();

    expect(Schema::hasColumn('violation_events', 'source_event_id'))->toBeTrue()
        ->and(Schema::hasColumn('session_participants', 'current_joined_at'))->toBeTrue()
        ->and(Schema::hasColumn('session_participants', 'attended_seconds'))->toBeTrue()
        ->and(Schema::hasColumn('session_participants', 'excused_at'))->toBeTrue()
        ->and(Schema::hasColumn('session_participants', 'excused_by'))->toBeTrue()
        ->and(Schema::hasColumn('session_participants', 'excuse_reason'))->toBeTrue()
        ->and(Schema::hasColumn('teacher_apologies', 'substitute_search_started_at'))->toBeTrue()
        ->and(Schema::hasColumn('teacher_apologies', 'last_substitute_search_at'))->toBeTrue()
        ->and(Schema::hasColumn('teacher_apologies', 'substitute_candidate_ids'))->toBeTrue()
        ->and(Schema::hasColumn('teacher_apologies', 'substitute_candidate_count'))->toBeTrue()
        ->and((int) DB::table('session_participants')
            ->where('id', $participantId)
            ->value('attended_seconds'))->toBe(420)
        ->and(DB::table('violation_events')
            ->where('id', $violationId)
            ->value('source_event_id'))->toBeNull()
        ->and((int) DB::table('teacher_apologies')
            ->where('id', $apologyId)
            ->value('substitute_candidate_count'))->toBe(0);
});
