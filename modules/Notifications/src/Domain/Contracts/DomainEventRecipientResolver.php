<?php

declare(strict_types=1);

namespace Modules\Notifications\Domain\Contracts;

/**
 * Resolves user IDs for an event without exposing profiles or foreign models
 * to Notifications. The application composition layer may enrich profile IDs.
 */
interface DomainEventRecipientResolver
{
    /**
     * @param list<string> $audiences
     * @param list<string> $recipientFields
     * @param array<string, mixed> $payload
     * @return list<string>
     */
    public function resolve(
        string $eventKey,
        array $audiences,
        array $recipientFields,
        array $payload,
    ): array;
}
