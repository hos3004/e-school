<?php

declare(strict_types=1);

namespace Modules\Sessions\Database\Factories;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Sessions\Domain\Enums\SessionStatus;
use Modules\Sessions\Domain\Models\Session;
use Shared\Testing\Fixtures;

/**
 * @extends Factory<Session>
 */
final class SessionFactory extends Factory
{
    protected $model = Session::class;

    public function definition(): array
    {
        $start = CarbonImmutable::now('UTC')->addDays($this->faker->numberBetween(1, 14))
            ->setTime($this->faker->numberBetween(8, 18), 0, 0);
        $end = $start->addMinutes(60);

        return [
            'organization_id' => Fixtures::organizationId(),
            'schedule_id' => null,
            'group_id' => null,
            'course_id' => (string) Str::ulid(),
            'staff_profile_id' => Fixtures::staffProfileId(),
            'substitute_for_staff_id' => null,
            'makeup_for_session_id' => null,
            'session_type' => $this->faker->randomElement(['regular', 'makeup', 'assessment']),
            'status' => SessionStatus::Scheduled,
            'scheduled_start' => $start,
            'scheduled_end' => $end,
            'title' => [
                'ar' => 'حصة تجريبية: '.$this->faker->word(),
                'en' => 'Sample session: '.$this->faker->word(),
            ],
            'notes' => null,
        ];
    }

    public function withStatus(SessionStatus $status): static
    {
        return $this->state(fn (): array => ['status' => $status]);
    }
}
