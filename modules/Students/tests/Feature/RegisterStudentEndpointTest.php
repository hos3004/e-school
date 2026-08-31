<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Identity\Domain\Models\User;
use Modules\Students\Tests\Support\StudentsPestContext;

uses(RefreshDatabase::class);

it('does not expose the legacy direct student profile creation endpoint', function (): void {
    /** @var StudentsPestContext $this */
    $this->actingAs(User::factory()->create())
        ->postJson('/api/students', [
            'student_code' => 'LEGACY-DIRECT',
        ])
        ->assertMethodNotAllowed();
});
