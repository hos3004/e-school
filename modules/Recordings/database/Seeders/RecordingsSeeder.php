<?php

declare(strict_types=1);

namespace Modules\Recordings\Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Recordings\Domain\Enums\RecordingStatus;
use Modules\Recordings\Domain\Models\Recording;

/**
 * بيانات تجريبية للتسجيلات.
 *
 * التسجيل يرتبط بحصة وفصل يملكهما موديولان آخران — هذا البذر يعمل على
 * الحصص الموجودة فقط، وإن لم توجد يكتفي بالتحذير ولا يخترع بيانات موديول آخر.
 */
final class RecordingsSeeder extends Seeder
{
    public function run(): void
    {
        $organizationId = DB::table('organizations')->orderBy('created_at')->value('id');

        if (!is_string($organizationId) || $organizationId === '') {
            $this->command?->warn(__('recordings::messages.seeder_no_organization'));

            return;
        }

        $sessions = DB::table('sessions')
            ->where('organization_id', $organizationId)
            ->orderBy('scheduled_start')
            ->get(['id', 'status', 'finalized_at']);

        if ($sessions->isEmpty()) {
            $this->command?->warn(__('recordings::messages.seeder_no_sessions'));

            return;
        }

        foreach ($sessions as $session) {
            $classroomId = DB::table('classrooms')->where('session_id', $session->id)->value('id');

            if (!is_string($classroomId) || $classroomId === '') {
                continue;
            }

            Recording::query()->firstOrCreate(
                [
                    'provider' => 'demo-provider',
                    'external_recording_id' => 'demo-'.$session->id,
                ],
                [
                    'id' => (string) Str::ulid(),
                    'organization_id' => $organizationId,
                    'session_id' => $session->id,
                    'classroom_id' => $classroomId,
                    'status' => RecordingStatus::Ready,
                    'duration_seconds' => 3600,
                    'size_bytes' => 350_000_000,
                    'disk' => 'r2',
                    'path' => 'recordings/demo/'.$session->id.'.mp4',
                    'thumbnail_path' => 'recordings/demo/'.$session->id.'.png',
                    'available_from' => CarbonImmutable::now('UTC')->subDay(),
                    'expires_at' => CarbonImmutable::now('UTC')->addDays((int) config('recordings.retention_days')),
                ],
            );
        }
    }
}
