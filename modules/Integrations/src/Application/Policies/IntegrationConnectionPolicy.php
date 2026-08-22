<?php

declare(strict_types=1);

namespace Modules\Integrations\Application\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Integrations\Domain\Models\IntegrationConnection;

/**
 * سياسة الاتصالات بالمزوّدين — الملكية بالمؤسسة أولًا ثم بوابة الصلاحيات.
 */
final class IntegrationConnectionPolicy
{
    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('integrations.connection.view_any');
    }

    public function view(Authenticatable&Authorizable $user, IntegrationConnection $connection): bool
    {
        return $user->can('integrations.connection.view')
            && $connection->organization_id === $this->organizationId($user);
    }

    public function create(Authenticatable&Authorizable $user): bool
    {
        return $user->can('integrations.connection.create');
    }

    public function update(Authenticatable&Authorizable $user, IntegrationConnection $connection): bool
    {
        return $user->can('integrations.connection.update')
            && $connection->organization_id === $this->organizationId($user);
    }

    public function delete(Authenticatable&Authorizable $user, IntegrationConnection $connection): bool
    {
        return $user->can('integrations.connection.delete')
            && $connection->organization_id === $this->organizationId($user);
    }

    public function activate(Authenticatable&Authorizable $user, IntegrationConnection $connection): bool
    {
        return $user->can('integrations.connection.activate')
            && $connection->organization_id === $this->organizationId($user);
    }

    public function disable(Authenticatable&Authorizable $user, IntegrationConnection $connection): bool
    {
        return $user->can('integrations.connection.disable')
            && $connection->organization_id === $this->organizationId($user);
    }

    private function organizationId(Authenticatable $user): string
    {
        return (string) data_get($user, 'organization_id', '');
    }
}
