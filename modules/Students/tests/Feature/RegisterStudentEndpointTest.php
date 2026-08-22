<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Identity\Domain\Models\User;

uses(RefreshDatabase::class);

it('does not expose the legacy direct student profile creation endpoint', function (): void {
    $this->actingAs(User::factory()->create())
        ->postJson('/api/students', [
            'student_code' => 'LEGACY-DIRECT',
        ])
        ->assertMethodNotAllowed();
});
