<?php

declare(strict_types=1);

namespace Modules\Reporting\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Reporting\Domain\Enums\DigestRecipientType;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

/**
 * إعداد مستلم التقارير الشهرية المجمَّعة للبرامج — سطر واحد عام لكل مؤسسة
 * (الإعداد الأدنى المطلوب من العميل)، وعمود `program_id` مؤجَّل لتخصيص
 * لاحق اختياري لكل برنامج.
 *
 * `recipient_user_id` عمود عادي — نموذجه (User) مملوك لموديول Identity
 * ولا يُستورد هنا؛ الحل عبر `UserAccountDirectory` وقت العرض والإرسال.
 *
 * @property string $id
 * @property string $organization_id
 * @property string|null $program_id
 * @property DigestRecipientType $recipient_type
 * @property string|null $recipient_user_id
 * @property string|null $custom_email
 * @property int $lock_version
 */
final class ProgramDigestRecipientSetting extends Model
{
    use HasModuleFactory;
    use HasUlid;

    protected $table = 'reporting_program_digest_recipients';

    protected $fillable = [
        'organization_id',
        'program_id',
        'recipient_type',
        'recipient_user_id',
        'custom_email',
        'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'recipient_type' => DigestRecipientType::class,
            'lock_version' => 'int',
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

    /**
     * السطر العام (بلا برنامج محدد) — الإعداد الافتراضي الأدنى المطلوب.
     *
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeGlobal(Builder $query): Builder
    {
        return $query->whereNull('program_id');
    }

    /**
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeForProgram(Builder $query, string $programId): Builder
    {
        return $query->where('program_id', $programId);
    }
}
