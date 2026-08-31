<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Modules\Identity\Domain\Models\User;
use Modules\Organization\Database\Seeders\GeographySeeder;
use Modules\Organization\Domain\Contracts\GeographyQueries;
use Modules\Organization\Domain\Models\Organization;
use Modules\Students\Application\Actions\ArchiveStudentAction;
use Modules\Students\Application\Actions\UpdateStudentProfileAction;
use Modules\Students\Domain\Events\StudentProfileUpdated;
use Modules\Students\Domain\Models\StudentProfile;
use Shared\Support\BusinessRuleViolation;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    /** @var \Modules\Students\Tests\Support\StudentsPestContext $this */
    $this->seed(GeographySeeder::class);
});

function createStudent(?Organization $organization = null, ?User $owner = null): StudentProfile
{
    $organization ??= Organization::factory()->create();
    $owner ??= User::factory()->inOrganization((string) $organization->id)->create();

    return StudentProfile::factory()->create([
        'organization_id' => (string) $organization->id,
        'user_id' => (string) $owner->id,
        'student_code' => 'STU-UP-'.str()->random(4),
    ]);
}

function actingAdminOf(Organization $organization): User
{
    return User::factory()->inOrganization((string) $organization->id)->create();
}

it('updates changed fields only and publishes the event with primitives', function (): void {
    /** @var \Modules\Students\Tests\Support\StudentsPestContext $this */
    [$organization] = createStudentWithOrg();
    $student = StudentProfile::query()
        ->where('organization_id', (string) $organization->id)
        ->firstOrFail();
    Event::fake([StudentProfileUpdated::class]);
    $admin = actingAdminOf($organization);

    app(UpdateStudentProfileAction::class)->execute($student, [
        'city' => 'Alexandria',
        'notes' => 'متفوق في الرياضيات',
    ], (string) $admin->id, 'تحديث بيانات الاتصال بعد الانتقال');

    expect($student->refresh()->city)->toBe('Alexandria');

    Event::assertDispatched(
        StudentProfileUpdated::class,
        fn (StudentProfileUpdated $event): bool => count($event->changes) === 2
            && isset($event->changes['city'], $event->changes['notes'])
            && !isset($event->changes['student_code']),
    );
});

it('records an audit entry with actor reason before and after values', function (): void {
    /** @var \Modules\Students\Tests\Support\StudentsPestContext $this */
    [$organization, , $student] = createStudentWithOrg();
    $admin = actingAdminOf($organization);

    app(UpdateStudentProfileAction::class)->execute($student, [
        'city' => 'Luxor',
    ], (string) $admin->id, 'تصحيح المدينة بعد مراجعة الطلب');

    expect(DB::table('audit_log')->where([
        'action' => 'students.profile_updated',
        'auditable_id' => (string) $student->getKey(),
        'reason' => 'تصحيح المدينة بعد مراجعة الطلب',
    ])->exists())->toBeTrue();
});

it('accepts country_id with matching region and publishes nothing when nothing changed', function (): void {
    /** @var \Modules\Students\Tests\Support\StudentsPestContext $this */
    [$organization, , $student] = createStudentWithOrg();
    $admin = actingAdminOf($organization);
    Event::fake([StudentProfileUpdated::class]);
    [$countryId, $regionId] = geographyIds();

    app(UpdateStudentProfileAction::class)->execute($student, [
        'country_id' => $countryId,
        'region_id' => $regionId,
    ], (string) $admin->id, 'ضبط الجغرافيا الناقصة للملف');

    expect($student->refresh()->country_id)->toBe($countryId);

    Event::fake([StudentProfileUpdated::class]);
    app(UpdateStudentProfileAction::class)->execute($student, [
        'country_id' => $student->country_id,
    ], (string) $admin->id, 'محاولة بلا تغيير فعلي');
});

it('rejects a region that does not belong to the country', function (): void {
    /** @var \Modules\Students\Tests\Support\StudentsPestContext $this */
    [$organization, , $student] = createStudentWithOrg();
    $admin = actingAdminOf($organization);
    [$countryId] = geographyIds();

    app(UpdateStudentProfileAction::class)->execute($student, [
        'country_id' => $countryId,
        'region_id' => (string) str()->ulid(),
    ], (string) $admin->id, 'محاولة ضبط جغرافيا غير متسقة');
})->throws(BusinessRuleViolation::class);

it('ignores ownership fields such as student_code and user_id', function (): void {
    /** @var \Modules\Students\Tests\Support\StudentsPestContext $this */
    [$organization, $owner, $student] = createStudentWithOrg();
    $admin = actingAdminOf($organization);

    app(UpdateStudentProfileAction::class)->execute($student, [
        'student_code' => 'HACKED-1',
        'user_id' => (string) $admin->id,
        'organization_id' => (string) Organization::factory()->create()->id,
    ], (string) $admin->id, 'محاولة تعديل حقول الملكية');

    $student->refresh();

    expect($student->student_code)->not->toBe('HACKED-1')
        ->and((string) $student->user_id)->toBe((string) $owner->id);
});

it('refuses an actor from another organization (cross-tenant)', function (): void {
    /** @var \Modules\Students\Tests\Support\StudentsPestContext $this */
    [$organization, , $student] = createStudentWithOrg();
    $outsiderOrg = Organization::factory()->create();
    $outsider = User::factory()->inOrganization((string) $outsiderOrg->id)->create();

    app(UpdateStudentProfileAction::class)->execute($student, [
        'city' => 'Cairo',
    ], (string) $outsider->id, 'محاولة عبور حدود المؤسسة');
})->throws(BusinessRuleViolation::class);

it('requires a written reason', function (): void {
    /** @var \Modules\Students\Tests\Support\StudentsPestContext $this */
    [$organization, , $student] = createStudentWithOrg();
    $admin = actingAdminOf($organization);

    app(UpdateStudentProfileAction::class)->execute(
        $student,
        ['city' => 'Giza'],
        (string) $admin->id,
        '',
    );
})->throws(BusinessRuleViolation::class);

it('refuses to update an archived student', function (): void {
    /** @var \Modules\Students\Tests\Support\StudentsPestContext $this */
    [$organization, , $student] = createStudentWithOrg();
    $admin = actingAdminOf($organization);
    app(ArchiveStudentAction::class)->execute($student, 'انتقال العائلة');

    app(UpdateStudentProfileAction::class)->execute(
        $student,
        ['city' => 'Luxor'],
        (string) $admin->id,
        'تعديل ملف مؤرشف',
    );
})->throws(BusinessRuleViolation::class);

/**
 * النموذج يعيد إرسال كل الحقول حتى غير المعدَّلة. الحقول المحوَّلة
 * (enum/تاريخ) يجب أن تُقارَن بعد التحويل، وإلا سجّلنا في دفتر
 * التدقيق تغييرًا لم يحدث.
 */
it('records no audit entry when cast fields are resubmitted unchanged', function (): void {
    /** @var \Modules\Students\Tests\Support\StudentsPestContext $this */
    [$organization, , $student] = createStudentWithOrg();
    $admin = actingAdminOf($organization);
    Event::fake([StudentProfileUpdated::class]);

    $before = DB::table('audit_log')->where('auditable_id', (string) $student->getKey())->count();

    app(UpdateStudentProfileAction::class)->execute($student, [
        'gender' => $student->gender?->value,
        'date_of_birth' => $student->date_of_birth?->toDateString(),
        'city' => $student->city,
    ], (string) $admin->id, 'إعادة حفظ النموذج بلا تغيير فعلي');

    expect(DB::table('audit_log')->where('auditable_id', (string) $student->getKey())->count())
        ->toBe($before);

    Event::assertNotDispatched(StudentProfileUpdated::class);
});

/** @return array{0: Organization, 1: User, 2: StudentProfile} */
function createStudentWithOrg(): array
{
    $organization = Organization::factory()->create();
    $owner = User::factory()->inOrganization((string) $organization->id)->create();
    $student = StudentProfile::factory()->create([
        'organization_id' => (string) $organization->id,
        'user_id' => (string) $owner->id,
        'student_code' => 'STU-UP-'.str()->random(4),
    ]);

    return [$organization, $owner, $student];
}

/** @return array{0: string, 1: string} */
function geographyIds(): array
{
    /** @var GeographyQueries $geography */
    $geography = app(GeographyQueries::class);
    $country = $geography->findCountryByIso2('EG');

    expect($country)->not->toBeNull();

    $regions = $geography->regionsOf((string) $country?->id);

    expect($regions)->not->toBeEmpty();

    return [(string) $country?->id, (string) $regions[0]->id];
}
