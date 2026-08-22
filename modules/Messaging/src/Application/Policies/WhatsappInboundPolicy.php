<?php

declare(strict_types=1);

namespace Modules\Messaging\Application\Policies;

use Modules\Messaging\Domain\Models\WhatsappInbound;

/**
 * سياسة رسائل واتساب الواردة.
 */
final class WhatsappInboundPolicy
{
    public function viewAny($user): bool
    {
        return $user->can('messaging.whatsapp_inbound.view_any');
    }

    public function view($user, WhatsappInbound $inbound): bool
    {
        return $user->can('messaging.whatsapp_inbound.view')
            && $inbound->organization_id === $user->organization_id;
    }

    public function create($user): bool
    {
        return $user->can('messaging.whatsapp_inbound.create');
    }

    public function update($user, WhatsappInbound $inbound): bool
    {
        return $user->can('messaging.whatsapp_inbound.update')
            && $inbound->organization_id === $user->organization_id;
    }

    public function delete($user, WhatsappInbound $inbound): bool
    {
        return $user->can('messaging.whatsapp_inbound.delete')
            && $inbound->organization_id === $user->organization_id;
    }

    public function handle($user, WhatsappInbound $inbound): bool
    {
        return $user->can('messaging.whatsapp_inbound.handle')
            && $inbound->organization_id === $user->organization_id;
    }
}
