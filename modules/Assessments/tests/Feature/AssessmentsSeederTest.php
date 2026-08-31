<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Modules\Assessments\Database\Seeders\AssessmentsSeeder;
use Modules\Assessments\Domain\Models\AssessmentAttempt;

it('seeds a graded attempt with its audit record', function (): void {
    $this->seed(AssessmentsSeeder::class);

    $attempt = AssessmentAttempt::query()->whereNotNull('graded_at')->firstOrFail();

    expect($attempt->score)->toBe(70)
        ->and($attempt->passed)->toBeTrue()
        ->and($attempt->graded_by)->not->toBeNull()
        ->and(
            DB::table('audit_log')
                ->where('action', 'assessments.attempt_graded')
                ->where('auditable_id', (string) $attempt->getKey())
                ->exists(),
        )->toBeTrue();
});
