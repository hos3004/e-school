<?php

declare(strict_types=1);

namespace Modules\Integrations\Application\Policies;

use Modules\Integrations\Domain\Models\IntegrationWebhookDelivery;

/**
 * سياسة إيصالات Webhook — الملكية عبر اتصال المؤسسة.
 */
final class IntegrationWebhookDeliveryPolicy
{
    public function viewAny($user): bool
    {
        return $user->can('integrations.delivery.view_any');
    }

    public function view($user, IntegrationWebhookDelivery $delivery): bool
    {
        return $user->can('integrations.delivery.view')
            && (string) $delivery->connection()->value('organization_id') === (string) $user->organization_id;
    }

    public function create($user): bool
    {
        return $user->can('integrations.delivery.create');
    }

    /** لا تعديل يدوي لمحتوى الإيصال — التسوية تمر عبر أفعال التسوية. */
    public function update($user, IntegrationWebhookDelivery $delivery): bool
    {
        return false;
    }

    public function delete($user, IntegrationWebhookDelivery $delivery): bool
    {
        return $user->can('integrations.delivery.delete')
            && (string) $delivery->connection()->value('organization_id') === (string) $user->organization_id;
    }

    public function requeue($user, IntegrationWebhookDelivery $delivery): bool
    {
        return $user->can('integrations.delivery.requeue')
            && (string) $delivery->connection()->value('organization_id') === (string) $user->organization_id;
    }
}
