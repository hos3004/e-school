<?php

declare(strict_types=1);

namespace App\Http\Requests\Console;

use Illuminate\Foundation\Http\FormRequest;

/**
 * شكل الطلب فقط — HTTP-level sanity (أنواع وحدود عامة). القواعد التجارية
 * الحقيقية (الجمهور المتناقض، حدود الإغلاق التلقائي، صحة الروابط...) تعيش
 * كلها في SavePopupCampaignAction ولا تُكرَّر هنا؛ نفس Action يخدم هذه
 * اللوحة ولوحة Filament القديمة معًا فيبقى السلوك متطابقًا.
 */
final class SavePopupMessageCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'internal_name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string'],
            'title' => ['required', 'array'],
            'title.ar' => ['required', 'string'],
            'title.en' => ['nullable', 'string'],
            'title.fr' => ['nullable', 'string'],
            'body' => ['required', 'array'],
            'body.ar' => ['required', 'string'],
            'body.en' => ['nullable', 'string'],
            'body.fr' => ['nullable', 'string'],
            'audiences' => ['required', 'array'],
            'audiences.*' => ['string'],
            'excluded_audiences' => ['nullable', 'array'],
            'excluded_audiences.*' => ['string'],
            'placement' => ['required', 'string'],
            'display_mode' => ['nullable', 'string'],
            'page_key' => ['nullable', 'string'],
            'frequency' => ['required', 'string'],
            'is_dismissible' => ['nullable', 'boolean'],
            'requires_acknowledgement' => ['nullable', 'boolean'],
            'acknowledgement_label' => ['nullable', 'array'],
            'auto_dismiss_seconds' => ['nullable', 'integer'],
            'priority' => ['required', 'integer'],
            'starts_at' => ['required', 'string'],
            'ends_at' => ['nullable', 'string'],
            'action_type' => ['nullable', 'string'],
            'internal_action_target' => ['nullable', 'string'],
            'external_action_target' => ['nullable', 'string'],
            'action_label' => ['nullable', 'array'],
            'links' => ['nullable', 'array'],
            'links.*.text' => ['required_with:links', 'string'],
            'links.*.url' => ['required_with:links', 'string'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }

    /**
     * تحويل نموذج الواجهة (نوع إجراء + هدفان منفصلان) إلى شكل
     * SavePopupCampaignAction (action_type + action_target موحَّدان)،
     * بنفس تحويل CreatePopupCampaign/EditPopupCampaign في Filament تمامًا.
     *
     * @return array<string, mixed>
     */
    public function campaignAttributes(): array
    {
        $data = $this->validated();
        $actionType = (string) ($data['action_type'] ?? '');
        $actionTarget = match ($actionType) {
            'internal_page' => $data['internal_action_target'] ?? null,
            'external_url' => $data['external_action_target'] ?? null,
            default => null,
        };

        return collect($data)
            ->except(['reason', 'internal_action_target', 'external_action_target'])
            ->put('action_type', $actionType !== '' ? $actionType : null)
            ->put('action_target', $actionTarget)
            ->all();
    }

    public function reason(): string
    {
        return (string) $this->validated('reason');
    }
}
