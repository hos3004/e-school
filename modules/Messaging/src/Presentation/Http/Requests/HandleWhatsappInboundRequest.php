<?php

declare(strict_types=1);

namespace Modules\Messaging\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Messaging\Domain\Models\WhatsappInbound;

/**
 * طلب تعامل موظف مع رسالة واتساب واردة.
 */
final class HandleWhatsappInboundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('messaging.whatsapp_inbound.handle');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }

    public function inbound(): WhatsappInbound
    {
        /** @var WhatsappInbound $inbound */
        $inbound = $this->route('inbound');

        return $inbound;
    }
}
