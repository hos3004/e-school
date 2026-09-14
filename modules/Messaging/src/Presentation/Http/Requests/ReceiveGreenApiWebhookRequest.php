<?php

declare(strict_types=1);

namespace Modules\Messaging\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Integrations\Domain\Contracts\GreenApiConnections;

final class ReceiveGreenApiWebhookRequest extends FormRequest
{
    private ?string $organizationId = null;

    public function authorize(): bool
    {
        $instanceId = (string) data_get($this->all(), 'instanceData.idInstance', '');
        $this->organizationId = app(GreenApiConnections::class)
            ->organizationForWebhook($instanceId, (string) $this->header('Authorization', ''));

        return $this->organizationId !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'typeWebhook' => ['required', 'string', 'max:80'],
            'instanceData' => ['required', 'array'],
            'instanceData.idInstance' => ['required'],
            'idMessage' => ['sometimes', 'string', 'max:128'],
            'status' => ['sometimes', 'string', 'max:40'],
            'stateInstance' => ['sometimes', 'string', 'max:40'],
            'description' => ['sometimes', 'string', 'max:1000'],
            'sendByApi' => ['sometimes', 'boolean'],
            'timestamp' => ['sometimes', 'integer'],
            'senderData' => ['sometimes', 'array'],
            'senderData.chatId' => ['sometimes', 'string', 'max:100'],
            'messageData' => ['sometimes', 'array'],
        ];
    }

    public function organizationId(): string
    {
        return (string) $this->organizationId;
    }
}
