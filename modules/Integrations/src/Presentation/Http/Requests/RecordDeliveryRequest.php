<?php

declare(strict_types=1);

namespace Modules\Integrations\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Integrations\Domain\Enums\WebhookDirection;

/**
 * طلب تسجيل إيصال Webhook جديد في الطابور.
 */
final class RecordDeliveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('integrations.delivery.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'connection_id' => ['required', 'string', 'size:26', 'exists:integration_connections,id'],
            'event_type' => ['required', 'string', 'max:120'],
            'direction' => ['required', 'string', Rule::enum(WebhookDirection::class)],
            'payload' => ['nullable', 'array'],
        ];
    }
}
