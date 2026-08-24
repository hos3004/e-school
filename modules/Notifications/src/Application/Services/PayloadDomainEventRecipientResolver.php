<?php

declare(strict_types=1);

namespace Modules\Notifications\Application\Services;

use Modules\Notifications\Domain\Contracts\DomainEventRecipientResolver;

final readonly class PayloadDomainEventRecipientResolver implements DomainEventRecipientResolver
{
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

        return array_values(array_unique($ids));
    }
}
