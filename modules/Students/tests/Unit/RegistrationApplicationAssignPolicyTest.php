<?php

declare(strict_types=1);

namespace Modules\Students\Tests\Unit;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Students\Application\Policies\RegistrationApplicationPolicy;
use Modules\Students\Domain\Enums\RegistrationStatus;
use Modules\Students\Domain\Models\RegistrationApplication;
use Tests\TestCase;

/**
 * تفويض التسكين — الفردي والجماعي.
 *
 * لا فحص على اسم الدور في أي فرع هنا؛ القرار دائمًا من صلاحيات معلَنة
 * ومن تطابق المؤسسة.
 */
final class RegistrationApplicationAssignPolicyTest extends TestCase
{
    public function test_it_allows_assignment_for_a_user_holding_all_three_abilities(): void
    {
        $application = $this->application('org-1', RegistrationStatus::WaitingAssignment);
        $user = $this->user('org-1', ['student.view', 'enrollment.create', 'group.manage']);

        $this->assertTrue((new RegistrationApplicationPolicy)->assign($user, $application));
    }

    public function test_it_denies_assignment_when_any_single_ability_is_missing(): void
    {
        $application = $this->application('org-1', RegistrationStatus::WaitingAssignment);
        $policy = new RegistrationApplicationPolicy;

        foreach (['student.view', 'enrollment.create', 'group.manage'] as $missing) {
            $abilities = array_values(array_diff(
                ['student.view', 'enrollment.create', 'group.manage'],
                [$missing],
            ));

            $this->assertFalse(
                $policy->assign($this->user('org-1', $abilities), $application),
                "توقّعنا الرفض عند غياب الصلاحية «{$missing}».",
            );
        }
    }

    public function test_it_denies_assignment_across_organizations(): void
    {
        $application = $this->application('org-1', RegistrationStatus::WaitingAssignment);
        $user = $this->user('org-2', ['student.view', 'enrollment.create', 'group.manage']);

        $this->assertFalse((new RegistrationApplicationPolicy)->assign($user, $application));
    }

    public function test_it_denies_assignment_for_an_application_not_cleared_yet(): void
    {
        $application = $this->application('org-1', RegistrationStatus::Submitted);
        $user = $this->user('org-1', ['student.view', 'enrollment.create', 'group.manage']);

        $this->assertFalse((new RegistrationApplicationPolicy)->assign($user, $application));
    }

    public function test_bulk_entry_point_requires_the_listing_ability(): void
    {
        $policy = new RegistrationApplicationPolicy;

        $this->assertTrue($policy->assignAny(
            $this->user('org-1', ['student.view.any', 'enrollment.create', 'group.manage']),
        ));
        $this->assertFalse($policy->assignAny(
            $this->user('org-1', ['enrollment.create', 'group.manage']),
        ));
    }

    public function test_individual_scheduling_requires_an_accepted_student_profile_and_schedule_permission(): void
    {
        $policy = new RegistrationApplicationPolicy;
        $application = $this->application('org-1', RegistrationStatus::WaitingAssignment);
        $application->student_profile_id = 'student-1';

        $this->assertTrue($policy->scheduleIndividual(
            $this->user('org-1', ['student.view', 'schedule.manage']),
            $application,
        ));
        $this->assertFalse($policy->scheduleIndividual(
            $this->user('org-1', ['student.view']),
            $application,
        ));

        $application->student_profile_id = null;
        $this->assertFalse($policy->scheduleIndividual(
            $this->user('org-1', ['student.view', 'schedule.manage']),
            $application,
        ));
    }

    public function test_individual_scheduling_bulk_entry_requires_listing_and_schedule_permissions(): void
    {
        $policy = new RegistrationApplicationPolicy;

        $this->assertTrue($policy->scheduleIndividualAny(
            $this->user('org-1', ['student.view.any', 'schedule.manage']),
        ));
        $this->assertFalse($policy->scheduleIndividualAny(
            $this->user('org-1', ['student.view.any']),
        ));
    }

    private function application(string $organizationId, RegistrationStatus $status): RegistrationApplication
    {
        $application = new RegistrationApplication;
        $application->organization_id = $organizationId;
        $application->status = $status;

        return $application;
    }

    /** @param list<string> $abilities */
    private function user(string $organizationId, array $abilities): Authenticatable&Authorizable
    {
        return new class($organizationId, $abilities) implements Authenticatable, Authorizable
        {
            /** @param list<string> $abilities */
            public function __construct(
                public string $organization_id,
                private array $abilities,
            ) {}

            /**
             * @param string|iterable<string> $abilities
             * @param mixed $arguments
             */
            public function can($abilities, $arguments = []): bool
            {
                return in_array($abilities, $this->abilities, true);
            }

            /**
             * @param string|iterable<string> $abilities
             * @param mixed $arguments
             */
            public function canAny($abilities, $arguments = []): bool
            {
                return false;
            }

            /**
             * @param string|iterable<string> $abilities
             * @param mixed $arguments
             */
            public function cant($abilities, $arguments = []): bool
            {
                return !$this->can($abilities, $arguments);
            }

            /**
             * @param string|iterable<string> $abilities
             * @param mixed $arguments
             */
            public function cannot($abilities, $arguments = []): bool
            {
                return $this->cant($abilities, $arguments);
            }

            public function getAuthIdentifierName(): string
            {
                return 'id';
            }

            public function getAuthIdentifier(): string
            {
                return 'user-1';
            }

            public function getAuthPasswordName(): string
            {
                return 'password';
            }

            public function getAuthPassword(): string
            {
                return '';
            }

            public function getRememberToken(): string
            {
                return '';
            }

            public function setRememberToken($value): void {}

            public function getRememberTokenName(): string
            {
                return 'remember_token';
            }
        };
    }
}
