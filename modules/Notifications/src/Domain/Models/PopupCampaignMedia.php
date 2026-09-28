<?php

declare(strict_types=1);

namespace Modules\Notifications\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Shared\Concerns\HasUlid;

/**
 * ملف ميديا مرفق بحملة منبثقة — صورة/فيديو/صوت/ملف قابل للتنزيل.
 *
 * القرص والمسار دائمًا كما كتبهما الرفع الفعلي
 * (StorePopupCampaignMediaController) فقط؛ لا مُنتِج شرعي آخر لهذا الصف.
 * أي متحكم يخدم الملف يجب أن يتحقق من بادئة المسار المتوقعة قبل الوصول
 * للقرص — نفس نمط ShowWallAttachmentController في موديول Messaging.
 *
 * @property string $id
 * @property string $campaign_id
 * @property string $kind
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property string $mime_type
 * @property int $size_bytes
 * @property bool|null $has_sound
 * @property int $position
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
final class PopupCampaignMedia extends Model
{
    use HasUlid;

    protected $table = 'popup_campaign_media';

    protected $fillable = [
        'campaign_id',
        'kind',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size_bytes',
        'has_sound',
        'position',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'has_sound' => 'boolean',
            'position' => 'integer',
        ];
    }

    /** @return BelongsTo<PopupCampaign, $this> */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(PopupCampaign::class, 'campaign_id');
    }
}
