<?php

declare(strict_types=1);

namespace Modules\Integrations\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * طلب تسوية نتيجة محاولة إيصال Webhook.
 */
final class SettleDeliveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('integrations.delivery.update');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'success' => ['required', 'boolean'],
            'response_code' => ['nullable', 'integer', 'min:100', 'max:599'],
            'response_body' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
