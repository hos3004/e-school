<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Modules\Identity\Domain\Models\User;
use Modules\Students\Application\Actions\ArchiveStudentAction;
use Modules\Students\Domain\Models\StudentProfile;
use Shared\Testing\Fixtures;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    /** @var \Modules\Students\Tests\Support\StudentsPestContext $this */
    Gate::define('student.view.any', fn ($user): bool => true);
    Gate::define('student.update', fn ($user): bool => true);

    $this->actor = User::factory()->create();
    $this->student = StudentProfile::factory()->create([
        'organization_id' => Fixtures::organizationId(),
        'user_id' => User::factory()->create()->getKey(),
        'student_code' => 'STU-API-'.str()->random(4),
        'city' => 'Cairo',
    ]);
});

it('requires authentication for student profile routes', function (): void {
    /** @var \Modules\Students\Tests\Support\StudentsPestContext $this */
    $this->getJson('/api/students')->assertUnauthorized();
});

it('shows a student profile', function (): void {
    /** @var \Modules\Students\Tests\Support\StudentsPestContext $this */
    $this->actingAs($this->actor)
        ->getJson('/api/students/'.$this->student->getKey())
        ->assertOk()
        ->assertJsonPath('data.id', (string) $this->student->getKey())
        ->assertJsonPath('data.city', 'Cairo');
});

it('hides archived students from the index and show by default', function (): void {
    /** @var \Modules\Students\Tests\Support\StudentsPestContext $this */
    app(ArchiveStudentAction::class)->execute($this->student, 'سبب تجريبي');

    $this->actingAs($this->actor)
        ->getJson('/api/students/'.$this->student->getKey())
        ->assertNotFound();
});

it('updates the profile through the API', function (): void {
    /** @var \Modules\Students\Tests\Support\StudentsPestContext $this */
    $this->actingAs($this->actor)
        ->patchJson('/api/students/'.$this->student->getKey(), [
            'city' => 'Aswan',
            'reason' => 'تحديث المدينة بناءً على طلب ولي الأمر',
        ])
        ->assertOk()
        ->assertJsonPath('data.city', 'Aswan');
});

it('archives with a reason through the API', function (): void {
    /** @var \Modules\Students\Tests\Support\StudentsPestContext $this */
    $this->actingAs($this->actor)
        ->deleteJson('/api/students/'.$this->student->getKey(), ['reason' => 'انسحاب من البرنامج'])
        ->assertNoContent();

    expect($this->student->refresh()->trashed())->toBeTrue();
});

it('rejects archiving without a reason', function (): void {
    /** @var \Modules\Students\Tests\Support\StudentsPestContext $this */
    $this->actingAs($this->actor)
        ->deleteJson('/api/students/'.$this->student->getKey())
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['reason']);
});

it('restores an archived student through the API', function (): void {
    /** @var \Modules\Students\Tests\Support\StudentsPestContext $this */
    app(ArchiveStudentAction::class)->execute($this->student, 'خطأ إداري');

    $this->actingAs($this->actor)
        ->postJson('/api/students/'.$this->student->getKey().'/restore')
        ->assertOk()
        ->assertJsonPath('data.id', (string) $this->student->getKey());

    expect($this->student->refresh()->trashed())->toBeFalse();
});

it('forbids update without any matching ability or ownership', function (): void {
    /** @var \Modules\Students\Tests\Support\StudentsPestContext $this */
    Gate::define('student.update', fn ($user): bool => false);

    $this->actingAs($this->actor)
        ->patchJson('/api/students/'.$this->student->getKey(), [
            'city' => 'Giza',
            'reason' => 'محاولة تحديث بلا صلاحية',
        ])
        ->assertForbidden();
});

it('forbids an authorized user from another organization and excludes its records from the index', function (): void {
    /** @var \Modules\Students\Tests\Support\StudentsPestContext $this */
    $otherOrganizationId = (string) Str::ulid();
    DB::table('organizations')->insert([
        'id' => $otherOrganizationId,
        'name' => json_encode(['ar' => 'مؤسسة اختبار أخرى', 'en' => 'Other Test Organization'], JSON_UNESCAPED_UNICODE),
        'slug' => 'other-test-'.strtolower(substr($otherOrganizationId, -8)),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $otherUser = User::factory()->inOrganization($otherOrganizationId)->create();
    $otherStudent = StudentProfile::factory()->create([
        'organization_id' => $otherOrganizationId,
        'user_id' => $otherUser->getKey(),
    ]);

    $this->actingAs($this->actor)
        ->getJson('/api/students/'.$otherStudent->getKey())
        ->assertForbidden();

    $response = $this->actingAs($this->actor)
        ->getJson('/api/students?organization_id='.$otherOrganizationId)
        ->assertOk();

    $data = $response->json('data');
    $this->assertIsArray($data);
    /** @var list<array{id: string}> $data */
    $visibleIds = collect($data)->pluck('id');

    expect($visibleIds)->toContain((string) $this->student->getKey());
    expect($visibleIds->contains((string) $otherStudent->getKey()))->toBeFalse();
});
