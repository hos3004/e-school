<?php

declare(strict_types=1);

namespace Modules\Guardians\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Guardians\Domain\Enums\GuardianRelationship;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

/**
 * رابط الوصي بالطالب — يحدد صلة القرابة وصلاحيات الوساطة والأقسام المرئية.
 *
 * فك الرابط أرشفة منطقية تحفظ تاريخ الوصاية والتدقيق، ويمكن إعادة الربط بسجل جديد.
 * student_profile_id معرّف خارجي يبقى عمودًا عاديًا.
 *
 * @property string $id
 * @property string $guardian_profile_id
 * @property string $student_profile_id
 * @property GuardianRelationship $relationship
 * @property bool $is_primary
 * @property bool $can_act_for
 * @property array<array-key, mixed>|null $visible_sections
 * @property CarbonImmutable|null $verified_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read GuardianProfile $guardian
 */
final class GuardianLink extends Model
{
    use HasModuleFactory;
    use HasUlid;
    use SoftDeletes;

    protected $table = 'guardian_links';

    protected $fillable = [
        'guardian_profile_id',
        'student_profile_id',
        'relationship',
        'is_primary',
        'can_act_for',
        'visible_sections',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'relationship' => GuardianRelationship::class,
            'is_primary' => 'boolean',
            'can_act_for' => 'boolean',
            'visible_sections' => 'array',
            'verified_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
            'deleted_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<GuardianProfile, $this>
     */
    public function guardian(): BelongsTo
    {
        return $this->belongsTo(GuardianProfile::class, 'guardian_profile_id');
    }

    /**
     * @param Builder<GuardianLink> $query
     * @return Builder<GuardianLink>
     */
    public function scopeForGuardian(Builder $query, string $guardianProfileId): Builder
    {
        return $query->where('guardian_profile_id', $guardianProfileId);
    }

    /**
     * @param Builder<GuardianLink> $query
     * @return Builder<GuardianLink>
     */
    public function scopeForStudent(Builder $query, string $studentProfileId): Builder
    {
        return $query->where('student_profile_id', $studentProfileId);
    }

    /**
     * @param Builder<GuardianLink> $query
     * @return Builder<GuardianLink>
     */
    public function scopePrimary(Builder $query): Builder
    {
        return $query->where('is_primary', true);
    }

    /**
     * @param Builder<GuardianLink> $query
     * @return Builder<GuardianLink>
     */
    public function scopeVerified(Builder $query): Builder
    {
        return $query->whereNotNull('verified_at');
    }

    /**
     * @param Builder<GuardianLink> $query
     * @return Builder<GuardianLink>
     */
    public function scopeWithRelationship(Builder $query, GuardianRelationship $relationship): Builder
    {
        return $query->where('relationship', $relationship);
    }
}
