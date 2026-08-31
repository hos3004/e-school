<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Modules\Identity\Domain\Models\User;
use Modules\Organization\Domain\Models\Organization;
use Modules\Recordings\Domain\Models\Recording;
use Modules\Recordings\Domain\Models\RecordingView;
use Modules\Recordings\Tests\Concerns\CreatesRecordingContext;

uses(RefreshDatabase::class, CreatesRecordingContext::class);

it('authorizes recording list show and view logging by assignment and download permission', function (): void {
    $context = $this->createSessionWithClassroom();
    $recording = Recording::factory()->ready()->create($context);
    $expired = Recording::factory()->pastRetention()->create($context);
    $processing = Recording::factory()->create($context);
    $teacherUserId = (string) DB::table('staff_profiles')
        ->where('id', DB::table('sessions')->where('id', $recording->session_id)->value('staff_profile_id'))
        ->value('user_id');
    $teacher = User::query()->findOrFail($teacherUserId);
    $broad = User::factory()->inOrganization($this->organizationId)->create();
    $unrelated = User::factory()->inOrganization($this->organizationId)->create();
    $foreignOrganization = Organization::factory()->create();
    $foreign = User::factory()->inOrganization((string) $foreignOrganization->id)->create();

    Gate::define('recording.view', static fn (): bool => true);
    Gate::define('recording.view.any', static fn (User $user): bool => (string) $user->id === (string) $broad->id);
    Gate::define('recording.download', static fn (): bool => false);

    foreach ([$teacher, $broad] as $viewer) {
        $this->actingAs($viewer)
            ->getJson('/api/recordings')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', (string) $recording->id);
        $this->actingAs($viewer)
            ->getJson('/api/recordings/'.$expired->id)
            ->assertForbidden();
        $this->actingAs($viewer)
            ->getJson('/api/recordings/'.$processing->id)
            ->assertForbidden();
    }

    $this->actingAs($unrelated)
        ->getJson('/api/recordings/'.$recording->id)
        ->assertForbidden();
    $this->actingAs($unrelated)
        ->postJson('/api/recordings/'.$recording->id.'/views', ['action' => 'view'])
        ->assertForbidden();
    expect(RecordingView::query()->count())->toBe(0);

    $this->actingAs($teacher)
        ->getJson('/api/recordings/'.$recording->id)
        ->assertOk();
    $this->actingAs($teacher)
        ->postJson('/api/recordings/'.$recording->id.'/views', ['action' => 'view'])
        ->assertNoContent();
    expect(RecordingView::query()->where('user_id', $teacher->id)->count())->toBe(1);

    $this->actingAs($teacher)
        ->postJson('/api/recordings/'.$recording->id.'/views', ['action' => 'download'])
        ->assertForbidden();
    expect(RecordingView::query()->where('action', 'download')->count())->toBe(0);

    config()->set('recordings.access.allow_download', true);
    Gate::define('recording.download', static fn (): bool => true);
    $this->actingAs($teacher)
        ->postJson('/api/recordings/'.$recording->id.'/views', ['action' => 'download'])
        ->assertNoContent();

    $this->actingAs($foreign)
        ->getJson('/api/recordings/'.$recording->id)
        ->assertNotFound();
});
