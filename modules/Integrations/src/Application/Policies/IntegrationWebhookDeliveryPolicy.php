<?php

declare(strict_types=1);

namespace Modules\Integrations\Application\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Integrations\Domain\Models\IntegrationWebhookDelivery;

/**
 * سياسة إيصالات Webhook — الملكية عبر اتصال المؤسسة.
 */
final class IntegrationWebhookDeliveryPolicy
{
    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('integrations.delivery.view_any');
    }

    public function view(Authenticatable&Authorizable $user, IntegrationWebhookDelivery $delivery): bool
    {
        return $user->can('integrations.delivery.view')
            && (string) $delivery->connection()->value('organization_id') === $this->organizationId($user);
    }

    public function create(Authenticatable&Authorizable $user): bool
    {
        return $user->can('integrations.delivery.create');
    }

    /** لا تعديل يدوي لمحتوى الإيصال — التسوية تمر عبر أفعال التسوية. */
    public function update(Authenticatable&Authorizable $user, IntegrationWebhookDelivery $delivery): bool
    {
        return false;
    }

    public function delete(Authenticatable&Authorizable $user, IntegrationWebhookDelivery $delivery): bool
    {
        return $user->can('integrations.delivery.delete')
            && (string) $delivery->connection()->value('organization_id') === $this->organizationId($user);
    }

    public function requeue(Authenticatable&Authorizable $user, IntegrationWebhookDelivery $delivery): bool
    {
        return $user->can('integrations.delivery.requeue')
            && (string) $delivery->connection()->value('organization_id') === $this->organizationId($user);
    }

    private function organizationId(Authenticatable $user): string
    {
        return (string) data_get($user, 'organization_id', '');
    }
}
