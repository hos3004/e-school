<?php

declare(strict_types=1);

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('never seeds demo identities in production', function (): void {
    $this->app->detectEnvironment(static fn (): string => 'production');

    try {
        $this->app->make(DatabaseSeeder::class)->run();
        expect(DB::table('users')->count())->toBe(0);
    } finally {
        $this->app->detectEnvironment(static fn (): string => 'testing');
    }
});

it('rejects a weak admin password and never echoes it', function (): void {
    $this->seed();

    $this->artisan('eschool:admin', [
        '--email' => 'secure-admin@example.test',
        '--password' => 'weak-password',
        '--name' => 'Secure Admin',
    ])->expectsOutputToContain('16')
        ->assertFailed();

    expect(DB::table('users')->where('email', 'secure-admin@example.test')->exists())->toBeFalse();
});
