<?php

declare(strict_types=1);

namespace Modules\Integrations\Domain\Contracts;

interface GreenApiConnections
{
    /** @return array<string, mixed> */
    public function view(string $organizationId): array;

    /** @return array{api_url: string, instance_id: string, token: string}|null */
    public function credentials(string $organizationId): ?array;

    public function isEnabled(string $organizationId): bool;

    public function isChannelEnabled(string $organizationId): bool;

    public function verify(string $apiUrl, string $instanceId, string $token): ?string;

    /** @param array<string, mixed> $data */
    public function save(string $organizationId, array $data, string $actorId, string $reason): void;

    public function organizationForWebhook(string $instanceId, string $authorization): ?string;

    public function registerWebhook(string $organizationId, string $actorId, string $reason): bool;

    public function recordState(string $organizationId, string $state): void;
}
