<?php

declare(strict_types=1);

use Modules\Academics\Database\Seeders\AcademicsSeeder;
use Modules\Academics\Domain\Models\Course;

it('seeds courses against the levels created by the academics seeder', function (): void {
    $this->seed(AcademicsSeeder::class);

    expect(Course::query()->where('code', 'C001')->firstOrFail()->level?->code)->toBe('L001')
        ->and(Course::query()->where('code', 'C002')->firstOrFail()->level?->code)->toBe('L002');
});
