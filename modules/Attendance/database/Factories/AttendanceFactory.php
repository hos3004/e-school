<?php

declare(strict_types=1);

namespace Modules\Attendance\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Attendance\Domain\Enums\AttendanceStatus;
use Modules\Attendance\Domain\Models\Attendance;

/**
 * @extends Factory<Attendance>
 */
final class AttendanceFactory extends Factory
{
    protected $model = Attendance::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $derived = $this->faker->randomElement(AttendanceStatus::cases());

        return [
            'session_participant_id' => (string) Str::ulid(),
            'status' => $derived,
            'derived_status' => $derived,
            'attended_minutes' => $this->faker->numberBetween(0, 60),
            'joined_after_minutes' => $this->faker->numberBetween(0, 10),
            'left_before_minutes' => $this->faker->numberBetween(0, 10),
            'confirmed_by' => null,
            'confirmed_at' => null,
            'override_reason' => null,
        ];
    }

    /** حضور معتمد من المعلم. */
    public function confirmed(): static
    {
        return $this->state(fn (): array => [
            'confirmed_by' => (string) Str::ulid(),
            'confirmed_at' => $this->faker->dateTimeThisMonth(),
        ]);
    }

    /** تجاوز للحالة المشتقة بسبب موثّق. */
    public function overridden(?AttendanceStatus $to = null): static
    {
        return $this->state(function (array $attributes) use ($to): array {
            /** @var AttendanceStatus $derived */
            $derived = $attributes['derived_status'];

            $target = $to ?? collect(AttendanceStatus::cases())
                ->first(fn (AttendanceStatus $case): bool => $case !== $derived);

            return [
                'status' => $target,
                'override_reason' => __('attendance::messages.demo_override_reason'),
                'confirmed_by' => (string) Str::ulid(),
                'confirmed_at' => $this->faker->dateTimeThisMonth(),
            ];
        });
    }
}
