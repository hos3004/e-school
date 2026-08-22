<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Integrations\Database\Factories\IntegrationProviderFactory;
use Modules\Integrations\Domain\Models\IntegrationConnection;
use Shared\Testing\Fixtures;

it('preserves existing credentials across encryption migration and rollback', function (): void {
    /** @var Migration $migration */
    $migration = require base_path('modules/Integrations/database/migrations/2026_08_22_100000_store_encrypted_credentials_as_text.php');

    $organizationId = Fixtures::organizationId();
    $provider = IntegrationProviderFactory::new()->create();
    $connectionId = (string) Str::ulid();
    $now = CarbonImmutable::now('UTC');

    $migration->down();

    DB::insert(
        <<<'SQL'
            INSERT INTO integration_connections
                (id, organization_id, provider_id, status, credentials, created_at, updated_at)
            VALUES
                (?, ?, ?, ?, CAST(? AS jsonb), ?, ?)
            SQL,
        [
            $connectionId,
            $organizationId,
            (string) $provider->getKey(),
            'pending',
            json_encode(['api_key' => 'migration-secret'], JSON_THROW_ON_ERROR),
            $now,
            $now,
        ],
    );

    $migration->up();

    $connection = IntegrationConnection::query()->findOrFail($connectionId);
    $encrypted = (string) DB::table('integration_connections')
        ->where('id', $connectionId)
        ->value('credentials');

    expect($connection->credentials)->toBe(['api_key' => 'migration-secret'])
        ->and($encrypted)->not->toContain('migration-secret');

    $migration->down();

    $restored = DB::selectOne(
        "SELECT credentials ->> 'api_key' AS api_key FROM integration_connections WHERE id = ?",
        [$connectionId],
    );

    expect($restored?->api_key)->toBe('migration-secret');

    // Leave the schema in its migrated state for the remainder of the test process.
    $migration->up();

    expect(IntegrationConnection::query()->findOrFail($connectionId)->credentials)
        ->toBe(['api_key' => 'migration-secret']);
});
