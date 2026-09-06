<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Modules\Organization\Database\Seeders\GeographySeeder;
use Modules\Organization\Domain\Contracts\GeographyQueries;
use Modules\Students\Domain\Models\RegistrationApplication;
use Modules\Students\Domain\Models\RegistrationForm;
use Shared\Testing\Fixtures;
use Tests\TestCase;

interface ConsoleRegistrationOfferingMigration
{
    public function up(): void;

    public function down(): void;
}

final class ConsoleRegistrationMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_preserves_existing_general_forms_and_rolls_back_without_losing_requests(): void
    {
        $this->seed(GeographySeeder::class);
        $this->withoutVite();
        config(['admission.self_registration.enabled' => true]);
        $form = RegistrationForm::query()->create([
            'organization_id' => Fixtures::organizationId(), 'slug' => 'legacy-general-registration',
            'title' => ['ar' => 'التسجيل العام القديم'], 'is_active' => true,
        ]);
        /** @var Migration&ConsoleRegistrationOfferingMigration $migration */
        $migration = require base_path('modules/Students/database/migrations/2026_09_06_120000_add_offering_to_registration_forms.php');
        $migration->down();
        $this->assertFalse(Schema::hasColumn('registration_forms', 'preferred_course_id'));
        $this->assertDatabaseHas('registration_forms', ['id' => $form->id, 'slug' => $form->slug, 'is_active' => true]);
        $migration->up();
        $this->assertNull($form->fresh()->preferred_program_id);
        $this->assertNull($form->fresh()->preferred_course_id);
        $country = app(GeographyQueries::class)->findCountryByIso2('EG');
        $this->post('/register/student/'.$form->slug, [
            'full_name' => 'طالب رابط قديم', 'email' => 'legacy-form@test.local', 'date_of_birth' => '2010-01-01',
            'gender' => 'male', 'country_id' => $country->id, 'region_id' => app(GeographyQueries::class)->regionsOf($country->id)[0]->id,
        ])->assertSessionHasNoErrors()->assertRedirect();
        $application = RegistrationApplication::query()->firstOrFail();
        $this->assertSame($form->id, $application->registration_form_id);
        $this->assertNull($application->preferred_course_id);
        $migration->down();
        $this->assertDatabaseHas('registration_applications', ['id' => $application->id, 'registration_form_id' => $form->id]);
        $migration->up();
        $this->get('/register/student/'.$form->slug)->assertOk();
    }
}
