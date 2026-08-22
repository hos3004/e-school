<?php

declare(strict_types=1);

namespace Modules\Discipline\Database\Factories;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Discipline\Domain\Enums\ViolationType;
use Modules\Discipline\Domain\Models\ViolationEvent;
use Modules\Discipline\Domain\ValueObjects\DisciplineWindow;
use Shared\Testing\Fixtures;

/**
 * @extends Factory<ViolationEvent>
 */
final class ViolationEventFactory extends Factory
{
    protected $model = ViolationEvent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $occurredAt = CarbonImmutable::instance($this->faker->dateTimeBetween('-2 months', 'now'));

        return [
            'organization_id' => Fixtures::organizationId(),
            'enrollment_id' => (string) Str::ulid(),
            'student_profile_id' => (string) Str::ulid(),
            'session_id' => null,
            'type' => ViolationType::UnexcusedAbsence,
            'occurred_at' => $occurredAt,
            'window_key' => DisciplineWindow::forDate($occurredAt)->key,
            'is_countable' => true,
        ];
    }

    /** مخالفة غير قابلة للعدّ (بعذر أو تأجيل موافق عليه). */
    public function notCountable(): static
    {
        return $this->state(fn (): array => [
            'type' => ViolationType::ExcusedAbsence,
            'is_countable' => false,
        ]);
    }

    /** مخالفة عنها عُفي بإدارة سببها. */
    public function waived(?string $waivedBy = null, ?string $reason = null): static
    {
        return $this->state(fn (): array => [
            'waived_by' => $waivedBy ?? Fixtures::userId(),
            'waived_at' => CarbonImmutable::now('UTC'),
            'waiver_reason' => $reason ?? 'عفو إداري تجريبي',
        ]);
    }
}
