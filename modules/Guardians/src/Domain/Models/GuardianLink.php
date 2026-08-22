<?php

declare(strict_types=1);

namespace Modules\Guardians\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Guardians\Domain\Enums\GuardianRelationship;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

/**
 * رابط الوصي بالطالب — يحدد صلة القرابة وصلاحيات الوساطة والأقسام المرئية.
 *
 * الجدول بلا deleted_at: فكّ الرابط حذف فعلي، لأن إعادة الربط تُنشأ من جديد
 * وتُوثَّق من أولها. student_profile_id معرّف خارجي يبقى عمودًا عاديًا.
 */
final class GuardianLink extends Model
{
    use HasModuleFactory;
    use HasUlid;

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
        ];
    }

    /**
     * @return BelongsTo<GuardianProfile, $this>
     */
    public function guardian(): BelongsTo
    {
        return $this->belongsTo(GuardianProfile::class, 'guardian_profile_id');
    }

    public function scopeForGuardian(Builder $query, string $guardianProfileId): Builder
    {
        return $query->where('guardian_profile_id', $guardianProfileId);
    }

    public function scopeForStudent(Builder $query, string $studentProfileId): Builder
    {
        return $query->where('student_profile_id', $studentProfileId);
    }

    public function scopePrimary(Builder $query): Builder
    {
        return $query->where('is_primary', true);
    }

    public function scopeVerified(Builder $query): Builder
    {
        return $query->whereNotNull('verified_at');
    }

    public function scopeWithRelationship(Builder $query, GuardianRelationship $relationship): Builder
    {
        return $query->where('relationship', $relationship);
    }
}
