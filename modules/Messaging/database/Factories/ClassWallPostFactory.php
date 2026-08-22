<?php

declare(strict_types=1);

namespace Modules\Messaging\Database\Factories;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Messaging\Domain\Models\ClassWallPost;
use Shared\Testing\Fixtures;

/**
 * @extends Factory<ClassWallPost>
 */
final class ClassWallPostFactory extends Factory
{
    protected $model = ClassWallPost::class;

    public function definition(): array
    {
        return [
            'organization_id' => Fixtures::organizationId(),
            'group_id' => (string) Str::ulid(),
            'user_id' => Fixtures::userId(),
            'body' => $this->faker->paragraph(2),
            'attachments' => [],
            'is_pinned' => false,
            'created_at' => CarbonImmutable::now('UTC'),
        ];
    }

    public function pinned(): static
    {
        return $this->state(fn (): array => ['is_pinned' => true]);
    }
}
