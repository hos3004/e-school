<?php

declare(strict_types=1);

use Modules\Integrations\Database\Seeders\IntegrationsSeeder;
use Modules\Integrations\Domain\Enums\ConnectionStatus;
use Modules\Integrations\Domain\Models\IntegrationConnection;
use Modules\Integrations\Domain\Models\IntegrationProvider;
use Shared\Testing\Fixtures;

it('retires existing zoom data before making bigbluebutton the video provider', function (): void {
    $organizationId = Fixtures::organizationId();
    (new IntegrationsSeeder)->run();

    $video = IntegrationProvider::query()->where('key', 'video_conferencing')->firstOrFail();
    $video->forceFill(['driver' => 'zoom'])->save();

    $connection = IntegrationConnection::query()->create([
        'organization_id' => $organizationId,
        'provider_id' => (string) $video->getKey(),
        'status' => ConnectionStatus::Active,
        'credentials' => ['token' => 'legacy-demo-token'],
        'settings' => ['meeting_mode' => 'legacy'],
        'activated_at' => now(),
    ]);

    (new IntegrationsSeeder)->run();
    (new IntegrationsSeeder)->run();

    expect($video->refresh()->driver)->toBe('bigbluebutton')
        ->and($video->is_active)->toBeTrue()
        ->and($connection->refresh()->status)->toBe(ConnectionStatus::Disabled)
        ->and($connection->credentials)->toBeNull()
        ->and($connection->settings)->toBeNull()
        ->and(IntegrationProvider::query()->where('driver', 'zoom')->exists())->toBeFalse()
        ->and(IntegrationProvider::query()->where('key', 'video_conferencing')->count())->toBe(1)
        ->and(IntegrationConnection::query()
            ->where('organization_id', $organizationId)
            ->where('provider_id', $video->id)
            ->count())->toBe(1);
});
