<?php

declare(strict_types=1);

namespace Modules\Students\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Modules\AccessControl\Database\Seeders\AccessControlSeeder;
use Modules\Identity\Domain\Models\User;
use Modules\Organization\Database\Seeders\GeographySeeder;
use Modules\Organization\Domain\Contracts\GeographyQueries;
use Modules\Students\Application\Actions\AcceptPublicRegistrationAction;
use Modules\Students\Application\Actions\SubmitRegistrationApplicationAction;
use Modules\Students\Domain\Enums\RegistrationStatus;
use Modules\Students\Domain\Models\RegistrationApplication;
use Shared\Support\BusinessRuleViolation;
use Shared\Testing\Fixtures;
use Tests\TestCase;

/**
 * يغطي هذا الملف ثغرة حقيقية: الطالب يكتب رقم هاتفه بصيغة محلية (00...) عند
 * التسجيل الذاتي، والقبول الإداري كان يمرر هذا الرقم كما هو لإنشاء الحساب
 * فيفشل بصمت دون أن تملك الإدارة حقل تعديل. الإصلاح يسمح للإدارة بتمرير رقم
 * مصحَّح عند القبول دون المساس برقم الطلب الأصلي.
 */
final class AcceptPublicRegistrationPhoneOverrideTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(GeographySeeder::class);
        $this->seed(AccessControlSeeder::class);
        Gate::before(static fn (): bool => true);
        $this->actingAs(User::query()->findOrFail(Fixtures::userId()));
    }

    public function test_admin_supplied_phone_override_lets_acceptance_succeed_despite_a_malformed_submitted_phone(): void
    {
        $application = $this->submittedApplicationWithMalformedPhone();

        $accepted = app(AcceptPublicRegistrationAction::class)->execute(
            $application->organization_id,
            $application->id,
            Fixtures::userId(),
            [
                'decision' => 'accept',
                'account_mode' => 'new',
                'username' => 'student.phone.override',
                'password' => 'Uncommon-Registration-Password-2026',
                'phone' => '+970599776130',
                'timezone' => 'Africa/Cairo',
            ],
        );

        $application = RegistrationApplication::query()->findOrFail($accepted);
        $this->assertNotNull($application->user_id);
        $account = User::query()->findOrFail($application->user_id);
        $this->assertSame('+970599776130', $account->phone);
        $this->assertSame('00970599776130', $application->phone, 'the original submission snapshot stays untouched');
    }

    public function test_without_an_admin_override_the_malformed_submitted_phone_still_blocks_acceptance(): void
    {
        $application = $this->submittedApplicationWithMalformedPhone();

        $this->expectException(BusinessRuleViolation::class);

        try {
            app(AcceptPublicRegistrationAction::class)->execute(
                $application->organization_id,
                $application->id,
                Fixtures::userId(),
                [
                    'decision' => 'accept',
                    'account_mode' => 'new',
                    'username' => 'student.no.override',
                    'password' => 'Uncommon-Registration-Password-2026',
                    'timezone' => 'Africa/Cairo',
                ],
            );
        } catch (BusinessRuleViolation $exception) {
            $this->assertSame('identity.phone_invalid', $exception->rule);

            throw $exception;
        }
    }

    private function submittedApplicationWithMalformedPhone(): RegistrationApplication
    {
        $orgId = Fixtures::organizationId();
        /** @var GeographyQueries $geography */
        $geography = app(GeographyQueries::class);
        $egypt = $geography->findCountryByIso2('EG');
        $regions = $geography->regionsOf($egypt->id);

        $application = RegistrationApplication::query()->create([
            'organization_id' => $orgId,
            'status' => RegistrationStatus::Draft,
            'full_name' => 'Khtam Student',
            'date_of_birth' => '2010-05-15',
            'gender' => 'female',
            'country_id' => $egypt->id,
            'region_id' => $regions[0]->id,
            'phone' => '00970599776130',
        ]);

        return app(SubmitRegistrationApplicationAction::class)->execute($application);
    }
}
