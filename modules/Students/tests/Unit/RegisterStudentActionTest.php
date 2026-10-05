<?php

declare(strict_types=1);

use Modules\Students\Application\Actions\RegisterStudentAction;
use Modules\Students\Tests\Support\StudentsPestContext;
use Shared\Support\BusinessRuleViolation;

it('blocks every legacy direct student profile creation attempt', function (): void {
    /** @var StudentsPestContext $this */
    try {
        (new RegisterStudentAction)->execute([
            'organization_id' => '01DIRECTPROFILEBLOCK000000',
            'user_id' => '01DIRECTPROFILEUSER0000000',
            'student_code' => 'LEGACY-DIRECT',
        ]);

        $this->fail('The legacy registration action did not block direct profile creation.');
    } catch (BusinessRuleViolation $exception) {
        expect($exception->rule)->toBe('students.direct_profile_creation_disabled')
            ->and($exception->getMessage())->toBe(__('students::errors.direct_profile_creation_disabled'));
    }
});
