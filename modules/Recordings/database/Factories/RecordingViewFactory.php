<?php

declare(strict_types=1);

namespace Modules\Recordings\Database\Factories;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Recordings\Domain\Models\Recording;
use Modules\Recordings\Domain\Models\RecordingView;
use Shared\Testing\Fixtures;

/**
 * @extends Factory<RecordingView>
 */
final class RecordingViewFactory extends Factory
{
    protected $model = RecordingView::class;

    public function definition(): array
    {
        return [
            'recording_id' => Recording::factory(),
            'user_id' => Fixtures::userId(),
            'viewed_at' => CarbonImmutable::now('UTC')->subMinutes($this->faker->numberBetween(1, 10080)),
            'ip_address' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
            'action' => $this->faker->randomElement(['view', 'download']),
        ];
    }
}
