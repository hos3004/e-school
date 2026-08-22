<?php

declare(strict_types=1);

use Modules\Assessments\Domain\Enums\AssessmentType;
use Modules\Assessments\Domain\Events\AssessmentArchived;
use Modules\Assessments\Domain\Events\AssessmentCreated;
use Modules\Assessments\Domain\Events\AttemptGraded;
use Modules\Assessments\Domain\Events\QuestionAdded;

it('keeps every event payload primitive-only and names events in the past tense', function (): void {
    $created = new AssessmentCreated(
        assessmentId: '01ASSESSMENT',
        organizationId: '01ORG',
        courseId: null,
        type: AssessmentType::Exam->value,
        totalScore: 100,
        passingScore: 60,
        maxAttempts: 2,
        createdBy: '01USER',
    );

    expect($created->module())->toBe('assessments')
        ->and($created->payload())->each(fn ($value) => $value->toBeScalar())
        ->and(str_ends_with($created->name(), '_created'))->toBeTrue();

    $archived = new AssessmentArchived('01ASSESSMENT', '01ORG');

    expect($archived->name())->toBe('assessments.assessment_archived')
        ->and($archived->payload())->toBe([
            'assessment_id' => '01ASSESSMENT',
            'organization_id' => '01ORG',
        ]);

    $graded = new AttemptGraded(
        assessmentId: '01ASSESSMENT',
        organizationId: '01ORG',
        attemptId: '01ATTEMPT',
        studentProfileId: '01STUDENT',
        attemptNumber: 1,
        score: 80,
        passed: true,
    );

    expect($graded->name())->toBe('assessments.attempt_graded')
        ->and($graded->toArray()['payload'])->toHaveKeys([
            'assessment_id', 'organization_id', 'attempt_id',
            'student_profile_id', 'attempt_number', 'score',
            'passed', 'reactivation_request_id',
        ]);
});

it('carries actor and correlation identifiers through the base event', function (): void {
    $event = new QuestionAdded(
        assessmentId: '01ASSESSMENT',
        organizationId: '01ORG',
        questionId: '01QUESTION',
        type: 'mcq',
        score: 10,
        sortOrder: 1,
        actorId: '01TEACHER',
        correlationId: '01CORR',
    );

    expect($event->actorId)->toBe('01TEACHER')
        ->and($event->correlationId)->toBe('01CORR')
        ->and($event->occurredAt->timezoneName)->toBe('UTC');
});
