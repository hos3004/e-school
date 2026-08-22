<?php

declare(strict_types=1);

namespace Modules\Audit\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Audit\Domain\Enums\AuditActorType;
use Modules\Audit\Domain\Models\AuditLog;
use Shared\Testing\Fixtures;

/**
 * @extends Factory<AuditLog>
 */
final class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    public function definition(): array
    {
        return [
            'organization_id' => Fixtures::organizationId(),
            'actor_id' => (string) Str::ulid(),
            'actor_type' => AuditActorType::User,
            'acting_for_user_id' => null,
            'action' => $this->faker->randomElement(['created', 'updated', 'logged_in']),
            'auditable_type' => 'Modules\\Sessions\\Domain\\Models\\Session',
            'auditable_id' => (string) Str::ulid(),
            'old_values' => ['status' => 'scheduled'],
            'new_values' => ['status' => 'completed'],
            'reason' => $this->faker->optional()->sentence(),
            'ip_address' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
            'correlation_id' => (string) Str::ulid(),
            'created_at' => now()->utc(),
        ];
    }

    /** قيدة من نظام آلي بلا فاعل بشري. */
    public function systemActor(): static
    {
        return $this->state(fn (): array => [
            'actor_id' => null,
            'actor_type' => AuditActorType::System,
        ]);
    }

    /** قيدة فعل حسّاس تتطلب سببًا مكتوبًا. */
    public function sensitive(string $action = 'payroll.entry_created'): static
    {
        return $this->state(fn (): array => [
            'action' => $action,
            'reason' => $this->faker->sentence(),
        ]);
    }
}
