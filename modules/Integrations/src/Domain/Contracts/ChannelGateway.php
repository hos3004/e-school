<?php

declare(strict_types=1);

namespace Modules\Integrations\Domain\Contracts;

use Modules\Integrations\Domain\ValueObjects\GatewayMessage;
use Modules\Integrations\Domain\ValueObjects\GatewayResult;

/**
 * بوابة عامة لتسليم رسالة عبر مزوّد خارجي أو قناة داخلية.
 *
 * العقد يمرّر قيمًا أولية فقط؛ لا يعرف نماذج الموديولات المستهلكة.
 */
interface ChannelGateway
{
    public function send(GatewayMessage $message): GatewayResult;
}
