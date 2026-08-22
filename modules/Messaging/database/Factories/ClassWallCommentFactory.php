<?php

declare(strict_types=1);

namespace Modules\Messaging\Database\Factories;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Messaging\Domain\Models\ClassWallComment;
use Shared\Testing\Fixtures;

/**
 * @extends Factory<ClassWallComment>
 */
final class ClassWallCommentFactory extends Factory
{
    protected $model = ClassWallComment::class;

    public function definition(): array
    {
        return [
            'organization_id' => Fixtures::organizationId(),
            'post_id' => null,
            'user_id' => Fixtures::userId(),
            'body' => $this->faker->sentence(8),
            'created_at' => CarbonImmutable::now('UTC'),
        ];
    }

    public function onPost(string $postId): static
    {
        return $this->state(fn (): array => ['post_id' => $postId]);
    }
}
