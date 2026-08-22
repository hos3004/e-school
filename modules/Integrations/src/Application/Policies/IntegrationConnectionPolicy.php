<?php

declare(strict_types=1);

namespace Modules\Integrations\Application\Policies;

use Modules\Integrations\Domain\Models\IntegrationConnection;

/**
 * سياسة الاتصالات بالمزوّدين — الملكية بالمؤسسة أولًا ثم بوابة الصلاحيات.
 */
final class IntegrationConnectionPolicy
{
    public function viewAny($user): bool
    {
        return $user->can('integrations.connection.view_any');
    }

    public function view($user, IntegrationConnection $connection): bool
    {
        return $user->can('integrations.connection.view')
            && (string) $connection->organization_id === (string) $user->organization_id;
    }

    public function create($user): bool
    {
        return $user->can('integrations.connection.create');
    }

    public function update($user, IntegrationConnection $connection): bool
    {
        return $user->can('integrations.connection.update')
            && (string) $connection->organization_id === (string) $user->organization_id;
    }

    public function delete($user, IntegrationConnection $connection): bool
    {
        return $user->can('integrations.connection.delete')
            && (string) $connection->organization_id === (string) $user->organization_id;
    }

    public function activate($user, IntegrationConnection $connection): bool
    {
        return $user->can('integrations.connection.activate')
            && (string) $connection->organization_id === (string) $user->organization_id;
    }

    public function disable($user, IntegrationConnection $connection): bool
    {
        return $user->can('integrations.connection.disable')
            && (string) $connection->organization_id === (string) $user->organization_id;
    }
}
