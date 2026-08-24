<?php

declare(strict_types=1);

namespace Modules\Recordings\Database\Factories;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Recordings\Domain\Enums\RecordingStatus;
use Modules\Recordings\Domain\Models\Recording;
use Shared\Testing\Fixtures;

/**
 * @extends Factory<Recording>
 */
final class RecordingFactory extends Factory
{
    protected $model = Recording::class;

    public function definition(): array
    {
        $availableFrom = CarbonImmutable::now('UTC')->subDays($this->faker->numberBetween(0, 10));
        $expiresAt = $availableFrom->addDays((int) config('recordings.retention_days'));

        return [
            'organization_id' => Fixtures::organizationId(),
            'session_id' => (string) Str::ulid(),
            'classroom_id' => (string) Str::ulid(),
            'provider' => 'bigbluebutton',
            'external_recording_id' => 'ext-'.Str::ulid(),
            'status' => RecordingStatus::Processing,
            'duration_seconds' => null,
            'size_bytes' => null,
            'disk' => 'r2',
            'path' => 'recordings/'.Str::ulid().'.mp4',
            'thumbnail_path' => null,
            'archive_driver' => null,
            'archive_path' => null,
            'archived_at' => null,
            'available_from' => $availableFrom,
            'expires_at' => $expiresAt,
        ];
    }

    public function withStatus(RecordingStatus $status): static
    {
        return $this->state(fn (): array => ['status' => $status]);
    }

    /** تسجيل جاهز بمدة وحجم محددين. */
    public function ready(): static
    {
        return $this->state(fn (): array => [
            'status' => RecordingStatus::Ready,
            'duration_seconds' => $this->faker->numberBetween(1800, 5400),
            'size_bytes' => $this->faker->numberBetween(50_000_000, 800_000_000),
            'thumbnail_path' => 'recordings/thumbs/'.Str::ulid().'.png',
        ]);
    }

    /** تسجيل قارب على الانتهاء — تجاوز موعد الاحتفاظ. */
    public function pastRetention(): static
    {
        return $this->state(fn (): array => [
            'status' => RecordingStatus::Ready,
            'expires_at' => CarbonImmutable::now('UTC')->subDays($this->faker->numberBetween(1, 5)),
        ]);
    }
}
