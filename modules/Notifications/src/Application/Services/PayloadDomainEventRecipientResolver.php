<?php

declare(strict_types=1);

namespace Modules\Notifications\Application\Services;

use Modules\AccessControl\Domain\Contracts\AccessControlQuerier;
use Modules\Identity\Domain\Contracts\UserAccountDirectory;
use Modules\Notifications\Domain\Contracts\DomainEventRecipientResolver;

final readonly class PayloadDomainEventRecipientResolver implements DomainEventRecipientResolver
{
    public function __construct(
        private AccessControlQuerier $accessControl,
        private UserAccountDirectory $accounts,
    ) {}

    public function resolve(
        string $eventKey,
        array $audiences,
        array $recipientFields,
        array $payload,
    ): array {
        $fields = [...$recipientFields, 'recipient_user_ids', 'recipient_user_id'];
        $ids = [];

        foreach (array_unique($fields) as $field) {
            if (!is_string($field) || $field === '') {
                continue;
            }

            $value = data_get($payload, $field);

            foreach (is_array($value) ? $value : [$value] as $id) {
                if (is_string($id) && trim($id) !== '') {
                    $ids[] = $id;
                }
            }
        }

        $organizationId = $payload['organization_id'] ?? null;
        $modelType = config('auth.providers.users.model');

        if (is_string($organizationId) && is_string($modelType)) {
            $roleMap = (array) config('notifications.audience_roles', []);
            $roleNames = [];

            foreach ($audiences as $audience) {
                if (!is_string($audience)) {
                    continue;
                }

                foreach ((array) ($roleMap[$audience] ?? []) as $roleName) {
                    if (is_string($roleName) && $roleName !== '') {
                        $roleNames[] = $roleName;
                    }
                }
            }

            $candidateIds = $this->accessControl->modelIdsForRoleNames(
                $modelType,
                array_values(array_unique($roleNames)),
            );
            $tenantAccounts = $this->accounts->findMany($organizationId, $candidateIds);
            $ids = [...$ids, ...array_keys($tenantAccounts)];
        }

        return array_values(array_unique($ids));
    }
}
