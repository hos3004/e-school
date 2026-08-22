<?php

declare(strict_types=1);

namespace Modules\Discipline\Database\Factories;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Discipline\Domain\Enums\DisciplineActionType;
use Modules\Discipline\Domain\Models\DisciplineAction;
use Modules\Discipline\Domain\Models\ViolationEvent;
use Modules\Discipline\Domain\ValueObjects\DisciplineWindow;
use Shared\Testing\Fixtures;

/**
 * @extends Factory<DisciplineAction>
 */
final class DisciplineActionFactory extends Factory
{
    protected $model = DisciplineAction::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Fixtures::organizationId(),
            'enrollment_id' => (string) Str::ulid(),
            'triggered_by_event_id' => ViolationEvent::factory(),
            'action' => DisciplineActionType::Notice,
            'threshold_reached' => 1,
            'window_key' => DisciplineWindow::current()->key,
            'is_automatic' => true,
            'applied_at' => CarbonImmutable::now('UTC'),
            'applied_by' => null,
            'notes' => null,
        ];
    }

    /** إجراء تجميد — الأعلى في سُلَّم الطالب. */
    public function freeze(): static
    {
        return $this->state(fn (): array => [
            'action' => DisciplineActionType::FreezeEnrollment,
        ]);
    }

    /** إجراء مُطبَّق يدويًا من الإدارة. */
    public function manual(string $appliedBy): static
    {
        return $this->state(fn (): array => [
            'is_automatic' => false,
            'applied_by' => $appliedBy,
        ]);
    }
}
