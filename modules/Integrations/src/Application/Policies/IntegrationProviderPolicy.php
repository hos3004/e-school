<?php

declare(strict_types=1);

namespace Modules\Integrations\Application\Policies;

use Modules\Integrations\Domain\Models\IntegrationProvider;

/**
 * سياسة مزوّدي التكاملات — لا فحص لأسماء الأدوار إطلاقًا.
 * كل فعل يمر عبر بوابة الصلاحيات integrations.provider.<action>.
 */
final class IntegrationProviderPolicy
{
    public function viewAny($user): bool
    {
        return $user->can('integrations.provider.view_any');
    }

    public function view($user, IntegrationProvider $provider): bool
    {
        return $user->can('integrations.provider.view');
    }

    public function create($user): bool
    {
        return $user->can('integrations.provider.create');
    }

    public function update($user, IntegrationProvider $provider): bool
    {
        return $user->can('integrations.provider.update');
    }

    public function delete($user, IntegrationProvider $provider): bool
    {
        return $user->can('integrations.provider.delete')
            && !$provider->connections()->exists();
    }
}
