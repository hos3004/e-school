<?php

declare(strict_types=1);

namespace Modules\SupportBot\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\SupportBot\Domain\Enums\TopicMode;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

/**
 * صفّ واحد من مصفوفة الحدود: ماذا يفعل البوت في هذا الموضوع أمام هذه الفئة.
 *
 * الجدول قابل للتحرير من اللوحة عمدًا — صاحب المنصة طلب صراحةً أن يضبط
 * المسموح والممنوع بنفسه دون نشر كود. ولأنه قابل للتحرير، لا يجوز أن يكون
 * الحارس الوحيد: فوقه سياج في الكود لا يُحرَّر (BotTopic::neverDisclosesFigures)،
 * وتحته صلاحيات المنصة التي تحكم البيانات أصلًا.
 *
 * @property string $id
 * @property string|null $organization_id
 * @property string $topic
 * @property string $audience
 * @property TopicMode $mode
 * @property string|null $reply_key
 * @property bool $is_active
 * @property string|null $updated_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
final class BotRule extends Model
{
    use HasModuleFactory;
    use HasUlid;

    protected $table = 'support_bot_rules';

    protected $fillable = [
        'organization_id',
        'topic',
        'audience',
        'mode',
        'reply_key',
        'is_active',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'mode' => TopicMode::class,
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

    /**
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeForOrganizationOrGlobal(Builder $query, string $organizationId): Builder
    {
        return $query->where(function (Builder $scoped) use ($organizationId): void {
            $scoped->where('organization_id', $organizationId)
                ->orWhereNull('organization_id');
        });
    }
}
