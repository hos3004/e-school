<?php

declare(strict_types=1);

namespace Modules\Students\Tests\Feature;

use Filament\Facades\Filament;
use Filament\Support\Contracts\TranslatableContentDriver;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Modules\Identity\Domain\Models\User;
use Modules\Organization\Database\Seeders\GeographySeeder;
use Modules\Organization\Domain\Contracts\GeographyQueries;
use Modules\Organization\Domain\Models\Organization;
use Modules\Staff\Domain\Enums\EmploymentType;
use Modules\Staff\Domain\Enums\StaffGender;
use Modules\Staff\Domain\Models\StaffProfile;
use Modules\Staff\Presentation\Filament\Resources\StaffProfileResource;
use Modules\Students\Domain\Enums\RegistrationStatus;
use Modules\Students\Domain\Enums\StudentGender;
use Modules\Students\Domain\Models\RegistrationApplication;
use Modules\Students\Domain\Models\StudentProfile;
use Modules\Students\Presentation\Filament\Resources\StudentProfileResource;
use Tests\TestCase;

final class AdminProfileHubTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_wizard_and_student_and_teacher_hubs_render(): void
    {
        $this->seed(GeographySeeder::class);
        Gate::before(static fn (): bool => true);
        Filament::setCurrentPanel('admin');

        $organization = Organization::factory()->create();
        $operator = User::factory()->inOrganization((string) $organization->id)->create();
        $studentUser = User::factory()->inOrganization((string) $organization->id)->create();
        $teacherUser = User::factory()->inOrganization((string) $organization->id)->create();
        [$countryId, $regionId] = $this->geographyIds();

        $student = StudentProfile::query()->create([
            'organization_id' => (string) $organization->id,
            'user_id' => (string) $studentUser->id,
            'student_code' => 'ST-HUB-001',
            'date_of_birth' => '2010-05-15',
            'gender' => StudentGender::Male,
            'country_id' => $countryId,
            'region_id' => $regionId,
            'joined_at' => now()->toDateString(),
        ]);
        RegistrationApplication::query()->create([
            'organization_id' => (string) $organization->id,
            'user_id' => (string) $studentUser->id,
            'student_profile_id' => (string) $student->id,
            'status' => RegistrationStatus::WaitingAssignment,
            'full_name' => 'Student Hub',
            'date_of_birth' => '2010-05-15',
            'gender' => StudentGender::Male,
            'country_id' => $countryId,
            'region_id' => $regionId,
            'email' => $studentUser->email,
        ]);
        $teacher = StaffProfile::query()->create([
            'organization_id' => (string) $organization->id,
            'user_id' => (string) $teacherUser->id,
            'staff_code' => 'TR-HUB-001',
            'employment_type' => EmploymentType::Contractor,
            'gender' => StaffGender::Male,
            'country_id' => $countryId,
            'region_id' => $regionId,
            'hired_at' => now()->toDateString(),
        ]);

        $this->actingAs($operator)
            ->get(StudentProfileResource::getUrl('create', panel: 'admin'))
            ->assertOk()
            ->assertSeeText(__('students::admin.onboarding.new_account'));

        $this->get(StudentProfileResource::getUrl('index', panel: 'admin'))
            ->assertOk()
            ->assertSeeText(__('students::admin.individual_quran.open_page'));

        $studentTable = StudentProfileResource::table(Table::make($this->tableLivewireStub()));
        $bulkActionNames = array_map(
            static fn (object $action): string => $action->getName(),
            $studentTable->getBulkActions(),
        );
        $this->assertContains('assignToGroup', $bulkActionNames);
        $this->assertContains('place_individual_quran', $bulkActionNames);

        $this->get(StudentProfileResource::getUrl('individual-quran', panel: 'admin'))
            ->assertOk()
            ->assertSeeText(__('students::admin.individual_quran.page_title'))
            ->assertSeeText(__('students::admin.individual_quran.page_description'));

        $this->get(StudentProfileResource::getUrl('view', ['record' => $student], panel: 'admin'))
            ->assertOk()
            ->assertSeeText('ST-HUB-001')
            ->assertSeeText(__('students::admin.hub.enrollments'));

        $this->get(StaffProfileResource::getUrl('view', ['record' => $teacher], panel: 'admin'))
            ->assertOk()
            ->assertSeeText('TR-HUB-001')
            ->assertSeeText(__('staff::admin.hub.qualifications'));
    }

    /** @return array{string, string} */
    private function geographyIds(): array
    {
        /** @var GeographyQueries $geography */
        $geography = app(GeographyQueries::class);
        $country = $geography->findCountryByIso2('EG');
        $regions = $geography->regionsOf((string) $country?->id);

        self::assertNotNull($country);
        self::assertNotEmpty($regions);

        return [(string) $country->id, $regions[0]->id];
    }

    private function tableLivewireStub(): HasTable
    {
        return new class extends Component implements HasTable
        {
            use InteractsWithTable;

            public function getTable(): Table
            {
                return StudentProfileResource::table(Table::make($this));
            }

            public function makeFilamentTranslatableContentDriver(): ?TranslatableContentDriver
            {
                return null;
            }
        };
    }
}
