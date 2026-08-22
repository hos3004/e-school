<?php

declare(strict_types=1);

namespace Modules\Integrations\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * طلب إعادة إحياء إيصال ميت.
 */
final class RequeueDeliveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('integrations.delivery.requeue');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => __('integrations::validation.reason_required'),
        ];
    }
}
