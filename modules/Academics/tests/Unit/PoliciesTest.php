<?php

declare(strict_types=1);

use Modules\Academics\Application\Policies\CoursePolicy;
use Modules\Academics\Application\Policies\LevelPolicy;
use Modules\Academics\Application\Policies\ProgramPolicy;
use Modules\Academics\Domain\Models\Course;
use Modules\Academics\Domain\Models\Level;
use Modules\Academics\Domain\Models\Program;

it('grants program access through declared abilities only', function (): void {
    $allowed = new class
    {
        public function can(string $ability): bool
        {
            return str_contains($ability, 'view');
        }

        public function getAuthIdentifier(): string
        {
            return 'user-1';
        }
    };

    $denied = new class
    {
        public function can(string $ability): bool
        {
            return false;
        }
    };

    $policy = new ProgramPolicy;
    $program = Program::factory()->make();

    expect($policy->viewAny($allowed))->toBeTrue()
        ->and($policy->view($allowed, $program))->toBeTrue()
        ->and($policy->create($allowed))->toBeFalse()
        ->and($policy->update($denied, $program))->toBeFalse()
        ->and($policy->delete($denied, $program))->toBeFalse();
});

it('gates level reorder behind its own ability', function (): void {
    $allowed = new class
    {
        public function can(string $ability): bool
        {
            return $ability === 'academics.levels.reorder';
        }
    };

    $denied = new class
    {
        public function can(string $ability): bool
        {
            return false;
        }
    };

    $policy = new LevelPolicy;
    $level = Level::factory()->make();

    expect($policy->reorder($allowed))->toBeTrue()
        ->and($policy->reorder($denied))->toBeFalse()
        ->and($policy->create($allowed))->toBeFalse()
        ->and($policy->update($allowed, $level))->toBeFalse();
});

it('separates course archive from update abilities', function (): void {
    $archiverOnly = new class
    {
        public function can(string $ability): bool
        {
            return $ability === 'academics.courses.archive';
        }
    };

    $updaterOnly = new class
    {
        public function can(string $ability): bool
        {
            return $ability === 'academics.courses.update';
        }
    };

    $policy = new CoursePolicy;
    $course = Course::factory()->make();

    expect($policy->delete($archiverOnly, $course))->toBeTrue()
        ->and($policy->update($archiverOnly, $course))->toBeFalse()
        ->and($policy->update($updaterOnly, $course))->toBeTrue()
        ->and($policy->delete($updaterOnly, $course))->toBeFalse();
});
