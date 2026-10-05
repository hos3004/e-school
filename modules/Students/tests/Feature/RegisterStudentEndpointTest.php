<?php

declare(strict_types=1);

use Modules\Identity\Domain\Models\User;
use Modules\Students\Tests\Support\StudentsPestContext;

it('does not expose the legacy direct student profile creation endpoint', function (): void {
    /** @var StudentsPestContext $this */
    $this->actingAs(User::factory()->create())
        ->postJson('/api/students', [
            'student_code' => 'LEGACY-DIRECT',
        ])
        ->assertMethodNotAllowed();
});
