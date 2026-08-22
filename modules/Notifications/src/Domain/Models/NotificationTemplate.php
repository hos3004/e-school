<?php

declare(strict_types=1);

namespace Modules\Notifications\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Shared\Concerns\HasUlid;

/**
 * قالب رسالة قابل للتخصيص على مستوى المؤسسة واللغة والقناة.
 *
 * @property string $id
 * @property string|null $organization_id
 * @property string $event_key
 * @property string $channel
 * @property string $locale
 * @property string|null $subject
 * @property string $body
 * @property string|null $provider_template_name
 * @property list<string> $parameters
 * @property bool $is_active
 */
final class NotificationTemplate extends Model
{
    use HasUlid;

    protected $table = 'notification_templates';

    protected $fillable = [
        'organization_id',
        'event_key',
        'channel',
        'locale',
        'subject',
        'body',
        'provider_template_name',
        'parameters',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'parameters' => 'array',
            'is_active' => 'bool',
        ];
    }

    /**
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
