<?php

declare(strict_types=1);

namespace Modules\SupportBot\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

/**
 * استهلاك مستخدم واحد في يوم واحد.
 *
 * التكلفة بميكرو-دولار كعدد صحيح: المشروع يمنع float في أي حساب مال، وحساب
 * التكلفة بكسور عشرية عبر آلاف النداءات ينحرف بصمت.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $user_id
 * @property CarbonImmutable $usage_date
 * @property int $requests
 * @property int $input_tokens
 * @property int $output_tokens
 * @property int $cost_micro_usd
 */
final class BotUsage extends Model
{
    use HasModuleFactory;
    use HasUlid;

    protected $table = 'support_bot_usage';

    protected $fillable = [
        'organization_id',
        'user_id',
        'usage_date',
        'requests',
        'input_tokens',
        'output_tokens',
        'cost_micro_usd',
    ];

    protected function casts(): array
    {
        return [
            'usage_date' => 'immutable_date',
            'requests' => 'int',
            'input_tokens' => 'int',
            'output_tokens' => 'int',
            'cost_micro_usd' => 'int',
        ];
    }

    /**
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeForOrganization(Builder $query, string $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }
}
