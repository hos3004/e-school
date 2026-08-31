<?php

declare(strict_types=1);

namespace Modules\Students\Tests\Unit;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Students\Application\Policies\RegistrationFormPolicy;
use Modules\Students\Application\Policies\RegistrationQuestionPolicy;
use Modules\Students\Domain\Models\RegistrationForm;
use Modules\Students\Domain\Models\RegistrationQuestion;
use Tests\TestCase;

final class RegistrationFormPolicyTest extends TestCase
{
    public function test_authorized_user_can_manage_forms_and_questions_in_their_organization(): void
    {
        $user = $this->user('org-1', ['student.create']);

        $this->assertTrue((new RegistrationFormPolicy)->update($user, $this->form('org-1')));
        $this->assertTrue((new RegistrationQuestionPolicy)->delete($user, $this->question('org-1')));
    }

    public function test_management_is_denied_without_the_declared_permission(): void
    {
        $user = $this->user('org-1', []);

        $this->assertFalse((new RegistrationFormPolicy)->create($user));
        $this->assertFalse((new RegistrationQuestionPolicy)->update($user, $this->question('org-1')));
    }

    public function test_record_actions_are_denied_across_organization_boundaries(): void
    {
        $user = $this->user('org-2', ['student.create']);

        $this->assertFalse((new RegistrationFormPolicy)->view($user, $this->form('org-1')));
        $this->assertFalse((new RegistrationQuestionPolicy)->delete($user, $this->question('org-1')));
    }

    public function test_registration_forms_are_never_deleted(): void
    {
        $user = $this->user('org-1', ['student.create']);

        $this->assertFalse((new RegistrationFormPolicy)->delete($user, $this->form('org-1')));
    }

    private function form(string $organizationId): RegistrationForm
    {
        $form = new RegistrationForm;
        $form->organization_id = $organizationId;

        return $form;
    }

    private function question(string $organizationId): RegistrationQuestion
    {
        $question = new RegistrationQuestion;
        $question->organization_id = $organizationId;

        return $question;
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

            public function can($abilities, $arguments = []): bool
            {
                return in_array($abilities, $this->abilities, true);
            }

            public function canAny($abilities, $arguments = []): bool
            {
                return false;
            }

            public function cant($abilities, $arguments = []): bool
            {
                return !$this->can($abilities, $arguments);
            }

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
