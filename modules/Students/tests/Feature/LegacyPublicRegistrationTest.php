<?php

declare(strict_types=1);

namespace Modules\Students\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Config;
use Modules\Organization\Database\Seeders\GeographySeeder;
use Modules\Organization\Domain\Contracts\GeographyQueries;
use Modules\Students\Domain\Models\RegistrationForm;
use Shared\Testing\Fixtures;
use Tests\TestCase;

final class LegacyPublicRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Fixtures::flush();
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->seed(GeographySeeder::class);
    }

    public function test_legacy_route_returns_not_found_without_a_published_form(): void
    {
        $this->get('/register/student')->assertNotFound();
        $this->post('/register/student', [])->assertNotFound();
    }

    public function test_legacy_route_uses_the_configured_published_default_form(): void
    {
        $form = $this->createDefaultForm();

        $this->get('/register/student')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Auth/RegisterStudent')
                ->where('registrationForm.slug', $form->slug));
    }

    public function test_legacy_route_submits_against_the_published_default_form(): void
    {
        $form = $this->createDefaultForm();
        /** @var GeographyQueries $geography */
        $geography = app(GeographyQueries::class);
        $country = $geography->findCountryByIso2('EG');
        $region = $geography->regionsOf($country->id)[0];

        $this->post('/register/student', [
            'full_name' => 'طالب جديد',
            'email' => 'legacy-registration@test.local',
            'date_of_birth' => '2010-05-15',
            'gender' => 'male',
            'country_id' => $country->id,
            'region_id' => $region->id,
        ])->assertRedirectContains('/register/submitted');

        $this->assertDatabaseHas('registration_applications', [
            'registration_form_id' => (string) $form->id,
            'email' => 'legacy-registration@test.local',
            'status' => 'submitted',
        ]);
    }

    private function createDefaultForm(): RegistrationForm
    {
        $organizationId = Fixtures::organizationId();
        Config::set('app.default_organization_id', $organizationId);
        Config::set('admission.self_registration.default_form_slug', 'legacy-default');

        return RegistrationForm::query()->create([
            'organization_id' => $organizationId,
            'slug' => 'legacy-default',
            'title' => ['ar' => 'نموذج التسجيل الافتراضي', 'en' => 'Default registration form'],
            'description' => ['ar' => 'وصف النموذج'],
            'is_active' => true,
        ]);
    }
}
