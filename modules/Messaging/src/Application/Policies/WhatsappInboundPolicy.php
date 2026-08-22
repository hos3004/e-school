<?php

declare(strict_types=1);

namespace Modules\Messaging\Application\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Messaging\Domain\Models\WhatsappInbound;

/**
 * سياسة رسائل واتساب الواردة.
 */
final class WhatsappInboundPolicy
{
    /** @param Authenticatable&object{organization_id: string} $user */
    public function viewAny(Authenticatable $user): bool
    {
        return $user->can('messaging.whatsapp_inbound.view_any');
    }

    /** @param Authenticatable&object{organization_id: string} $user */
    public function view(Authenticatable $user, WhatsappInbound $inbound): bool
    {
        return $user->can('messaging.whatsapp_inbound.view')
            && $inbound->organization_id === $user->organization_id;
    }

    /** @param Authenticatable&object{organization_id: string} $user */
    public function create(Authenticatable $user): bool
    {
        return $user->can('messaging.whatsapp_inbound.create');
    }

    /** @param Authenticatable&object{organization_id: string} $user */
    public function update(Authenticatable $user, WhatsappInbound $inbound): bool
    {
        return $user->can('messaging.whatsapp_inbound.update')
            && $inbound->organization_id === $user->organization_id;
    }

    /** @param Authenticatable&object{organization_id: string} $user */
    public function delete(Authenticatable $user, WhatsappInbound $inbound): bool
    {
        return $user->can('messaging.whatsapp_inbound.delete')
            && $inbound->organization_id === $user->organization_id;
    }

    /** @param Authenticatable&object{organization_id: string} $user */
    public function handle(Authenticatable $user, WhatsappInbound $inbound): bool
    {
        return $user->can('messaging.whatsapp_inbound.handle')
            && $inbound->organization_id === $user->organization_id;
    }
}
