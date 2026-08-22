<?php

declare(strict_types=1);

namespace Modules\Integrations\Application\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Integrations\Domain\Models\IntegrationProvider;

/**
 * سياسة مزوّدي التكاملات — لا فحص لأسماء الأدوار إطلاقًا.
 * كل فعل يمر عبر بوابة الصلاحيات integrations.provider.<action>.
 */
final class IntegrationProviderPolicy
{
    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('integrations.provider.view_any');
    }

    public function view(Authenticatable&Authorizable $user, IntegrationProvider $provider): bool
    {
        return $user->can('integrations.provider.view');
    }

    public function create(Authenticatable&Authorizable $user): bool
    {
        return $user->can('integrations.provider.create');
    }

    public function update(Authenticatable&Authorizable $user, IntegrationProvider $provider): bool
    {
        return $user->can('integrations.provider.update');
    }

    public function delete(Authenticatable&Authorizable $user, IntegrationProvider $provider): bool
    {
        return $user->can('integrations.provider.delete')
            && !$provider->connections()->exists();
    }
}
