<?php

declare(strict_types=1);

namespace Modules\Groups\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Groups\Domain\Models\Group;
use Modules\Groups\Domain\Models\GroupProgram;

/**
 * @extends Factory<GroupProgram>
 */
final class GroupProgramFactory extends Factory
{
    protected $model = GroupProgram::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'group_id' => Group::factory(),
            'program_id' => (string) Str::ulid(),
        ];
    }
}
