<?php

declare(strict_types=1);

namespace Modules\SupportBot\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\SupportBot\Domain\Enums\BotAudience;
use Modules\SupportBot\Domain\Enums\EntryKind;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

/**
 * نص قابل للتحرير من اللوحة: توجيه، أو معرفة، أو ردّ معدّ.
 *
 * organization_id فارغ يعني الصف العام المشحون مع النظام؛ صف المؤسسة يغطّيه.
 *
 * @property string $id
 * @property string|null $organization_id
 * @property EntryKind $kind
 * @property string $key
 * @property string $locale
 * @property string|null $title
 * @property string $body
 * @property list<string> $audiences
 * @property string|null $topic
 * @property int $priority
 * @property bool $is_active
 * @property string|null $created_by
 * @property string|null $updated_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 */
final class BotEntry extends Model
{
    use HasModuleFactory;
    use HasUlid;
    use SoftDeletes;

    protected $table = 'support_bot_entries';

    protected $fillable = [
        'organization_id',
        'kind',
        'key',
        'locale',
        'title',
        'body',
        'audiences',
        'topic',
        'priority',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'kind' => EntryKind::class,
            'audiences' => 'array',
            'priority' => 'int',
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
     * صفوف المؤسسة والصفوف العامة معًا. الترتيب يتم عند الحل لا هنا، لأن
     * التغطية تحتاج الصفين حاضرين للمقارنة.
     *
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

    /** قائمة فارغة تعني «لكل الفئات» — لا «لا أحد». */
    public function servesAudience(BotAudience $audience): bool
    {
        if ($this->audiences === []) {
            return true;
        }

        return in_array($audience->value, $this->audiences, true);
    }
}
