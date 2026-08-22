<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Modules\Identity\Domain\Models\User;
use Modules\Students\Application\Actions\ArchiveStudentAction;
use Modules\Students\Application\Actions\RegisterStudentAction;
use Shared\Testing\Fixtures;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Gate::define('students.view_any', fn ($user) => true);
    Gate::define('students.update_any', fn ($user) => true);
    Gate::define('students.archive_any', fn ($user) => true);
    Gate::define('students.restore_any', fn ($user) => true);

    $this->actor = User::factory()->create();
    $this->student = app(RegisterStudentAction::class)->execute([
        'organization_id' => Fixtures::organizationId(),
        'user_id' => User::factory()->create()->getKey(),
        'student_code' => 'STU-API-'.str()->random(4),
        'city' => 'Cairo',
    ]);
});

it('shows a student profile', function (): void {
    $this->actingAs($this->actor)
        ->getJson('/api/students/'.$this->student->getKey())
        ->assertOk()
        ->assertJsonPath('data.id', (string) $this->student->getKey())
        ->assertJsonPath('data.city', 'Cairo');
});

it('hides archived students from the index and show by default', function (): void {
    app(ArchiveStudentAction::class)->execute($this->student, 'سبب تجريبي');

    $this->actingAs($this->actor)
        ->getJson('/api/students/'.$this->student->getKey())
        ->assertNotFound();
});

it('updates the profile through the API', function (): void {
    $this->actingAs($this->actor)
        ->patchJson('/api/students/'.$this->student->getKey(), ['city' => 'Aswan'])
        ->assertOk()
        ->assertJsonPath('data.city', 'Aswan');
});

it('archives with a reason through the API', function (): void {
    $this->actingAs($this->actor)
        ->deleteJson('/api/students/'.$this->student->getKey(), ['reason' => 'انسحاب من البرنامج'])
        ->assertNoContent();

    expect($this->student->refresh()->trashed())->toBeTrue();
});

it('rejects archiving without a reason', function (): void {
    $this->actingAs($this->actor)
        ->deleteJson('/api/students/'.$this->student->getKey())
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['reason']);
});

it('restores an archived student through the API', function (): void {
    app(ArchiveStudentAction::class)->execute($this->student, 'خطأ إداري');

    $this->actingAs($this->actor)
        ->postJson('/api/students/'.$this->student->getKey().'/restore')
        ->assertOk()
        ->assertJsonPath('data.id', (string) $this->student->getKey());

    expect($this->student->refresh()->trashed())->toBeFalse();
});

it('forbids update without any matching ability or ownership', function (): void {
    Gate::define('students.update_any', fn ($user) => false);
    Gate::define('students.update_own', fn ($user) => false);

    $this->actingAs($this->actor)
        ->patchJson('/api/students/'.$this->student->getKey(), ['city' => 'Giza'])
        ->assertForbidden();
});
