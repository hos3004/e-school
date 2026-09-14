<?php

declare(strict_types=1);

namespace Modules\Notifications\Domain\Contracts;

interface ProviderDeliveryStatusRecorder
{
    public function record(string $organizationId, string $externalMessageId, string $status, string $description): void;
}
