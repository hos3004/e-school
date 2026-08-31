<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Organization\Database\Seeders\GeographySeeder;
use Modules\Organization\Domain\Contracts\GeographyQueries;
use Modules\Students\Domain\Models\RegistrationForm;
use Shared\Testing\Fixtures;
use Tests\TestCase;

final class PublicStudentRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private RegistrationForm $form;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(GeographySeeder::class);
        $this->form = RegistrationForm::query()->create([
            'organization_id' => Fixtures::organizationId(),
            'slug' => 'legacy-default-registration',
            'title' => ['ar' => 'نموذج التسجيل', 'en' => 'Registration form'],
            'is_active' => true,
        ]);
    }

    public function test_public_registration_page_can_be_rendered(): void
    {
        $response = $this->get('/register/student');

        $response->assertStatus(200);
    }

    public function test_student_can_submit_public_registration(): void
    {
        $response = $this->post('/register/student', $this->validStudentData());

        $response->assertStatus(302);
        $response->assertRedirectContains('/register/submitted');

        $this->assertDatabaseHas('registration_applications', [
            'email' => 'ahmed.public@example.com',
            'registration_form_id' => $this->form->id,
            'status' => 'submitted',
        ]);
    }

    public function test_legacy_registration_route_returns_not_found_without_a_published_form(): void
    {
        $this->form->update(['is_active' => false]);

        $this->get('/register/student')->assertNotFound();
        $this->post('/register/student', $this->validStudentData())->assertNotFound();
    }

    public function test_application_status_page_can_be_rendered(): void
    {
        $response = $this->get('/register/status/non-existent-ulid');

        $response->assertStatus(200);
    }

    /** @return array<string, string> */
    private function validStudentData(): array
    {
        /** @var GeographyQueries $geography */
        $geography = app(GeographyQueries::class);
        $country = $geography->findCountryByIso2('SA');

        return [
            'full_name' => 'أحمد علي',
            'email' => 'ahmed.public@example.com',
            'phone' => '+966500000000',
            'date_of_birth' => '2010-05-15',
            'gender' => 'male',
            'country_id' => $country->id,
            'region_id' => $geography->regionsOf($country->id)[0]->id,
            'notes' => 'طالب مستجد',
        ];
    }
}
