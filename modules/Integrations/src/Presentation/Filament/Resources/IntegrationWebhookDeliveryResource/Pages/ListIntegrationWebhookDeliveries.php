<?php

declare(strict_types=1);

namespace Modules\Integrations\Presentation\Filament\Resources\IntegrationWebhookDeliveryResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Integrations\Presentation\Filament\Resources\IntegrationWebhookDeliveryResource;

/**
 * صفحة فهرس IntegrationWebhookDeliveryResource — كان المورد مُعلنًا في اللوحة بلا صفحات، فلا مسار له ولا
 * سبيل لفتحه رغم ظهوره في الشيفرة.
 */
final class ListIntegrationWebhookDeliveries extends ListRecords
{
    protected static string $resource = IntegrationWebhookDeliveryResource::class;
}
