<?php

declare(strict_types=1);

use Modules\Assessments\Presentation\Filament\Resources\AssessmentAttemptResource;
use Modules\Assessments\Presentation\Filament\Resources\AssessmentResource;

it('hides and blocks assessment resources while the feature is disabled', function (): void {
    config()->set('features.assessments', false);

    expect(AssessmentResource::shouldRegisterNavigation())->toBeFalse()
        ->and(AssessmentResource::canAccess())->toBeFalse()
        ->and(AssessmentAttemptResource::shouldRegisterNavigation())->toBeFalse()
        ->and(AssessmentAttemptResource::canAccess())->toBeFalse();
});

it('registers assessment navigation only while the feature is enabled', function (): void {
    config()->set('features.assessments', true);

    expect(AssessmentResource::shouldRegisterNavigation())->toBeTrue()
        ->and(AssessmentAttemptResource::shouldRegisterNavigation())->toBeTrue();
});
