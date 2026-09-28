<?php

declare(strict_types=1);

namespace Modules\Notifications\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Notifications\Domain\Models\PopupCampaign;

/**
 * طلب رفع ملف ميديا لحملة منبثقة.
 *
 * kind يُدخله الأدمن صراحة ويُتحقق منه ضد قائمة مغلقة — لا يُستنتج من نوع
 * الملف المرفوع (لا "sniffing"). القرص والمسار لا يُقرآن من الطلب أبدًا؛
 * المتحكم وحده يكتبهما من config واسم الملف الفعلي المخزَّن.
 */
final class StorePopupCampaignMediaRequest extends FormRequest
{
    private const KINDS = ['image', 'video', 'audio', 'file'];

    public function authorize(): bool
    {
        /** @var PopupCampaign|null $campaign */
        $campaign = $this->route('campaign');

        return $campaign instanceof PopupCampaign
            && $this->user()?->can('update', $campaign) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $kind = (string) $this->input('kind');
        $isValidKind = in_array($kind, self::KINDS, true);

        /** @var array<string, mixed> $attachmentConfig */
        $attachmentConfig = $isValidKind
            ? (array) config("popups.attachments.{$kind}", [])
            : [];

        $maxKilobytes = (int) ($attachmentConfig['max_size_kilobytes'] ?? 0);
        /** @var list<string> $allowedMimes */
        $allowedMimes = (array) ($attachmentConfig['allowed_mime_types'] ?? []);

        return [
            'kind' => ['required', 'string', 'in:'.implode(',', self::KINDS)],
            'file' => [
                'required',
                'file',
                'max:'.($maxKilobytes > 0 ? $maxKilobytes : 1),
                'mimetypes:'.($allowedMimes !== [] ? implode(',', $allowedMimes) : 'application/x-forbidden'),
            ],
            // فقط لملفات الفيديو — يتحكم بها الأدمن يدويًا لأن الفحص الآلي
            // لوجود صوت في حاوية الفيديو غير متاح هنا.
            'has_sound' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'kind.required' => __('notifications::popups.errors.invalid_media_kind'),
            'kind.in' => __('notifications::popups.errors.invalid_media_kind'),
            'file.required' => __('notifications::popups.errors.invalid_media_kind'),
            'file.mimetypes' => __('notifications::popups.errors.invalid_media_kind'),
        ];
    }
}
