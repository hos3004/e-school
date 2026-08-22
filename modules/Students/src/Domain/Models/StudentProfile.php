<?php

declare(strict_types=1);

namespace Modules\Students\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Students\Database\Factories\StudentProfileFactory;
use Modules\Students\Domain\Enums\StudentGender;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

/**
 * ملف الطالب — الكيان الجذري لموديول Students.
 *
 * user_id و organization_id معرّفات خارجية تبقى أعمدة عادية؛ أي ارتباط
 * بنماذج موديولات أخرى يتم عبر الأحداث أو العقود، لا عبر علاقات Eloquent.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $user_id
 * @property string $student_code
 * @property CarbonImmutable|null $date_of_birth
 * @property StudentGender|null $gender
 * @property string|null $nationality
 * @property string|null $country @deprecated use country_id instead
 * @property string|null $country_id
 * @property string|null $region_id
 * @property string|null $city
 * @property string|null $preferred_language
 * @property CarbonImmutable|null $joined_at
 * @property string|null $notes
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 */
final class StudentProfile extends Model
{
    use HasModuleFactory;
    use HasUlid;
    use SoftDeletes;

    protected $table = 'student_profiles';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'user_id',
        'student_code',
        'date_of_birth',
        'gender',
        'nationality',
        'country',
        'country_id',
        'region_id',
        'city',
        'preferred_language',
        'joined_at',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_of_birth' => 'immutable_date',
            'gender' => StudentGender::class,
            'joined_at' => 'immutable_date',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
            'deleted_at' => 'immutable_datetime',
        ];
    }

    protected static function newFactory(): StudentProfileFactory
    {
        return StudentProfileFactory::new();
    }

    /**
     * الطلب الذي أنشأ هذا الملف عند القبول.
     *
     * @return HasOne<RegistrationApplication, $this>
     */
    public function registrationApplication(): HasOne
    {
        return $this->hasOne(RegistrationApplication::class, 'student_profile_id');
    }

    /**
     * حصر الاستعلام على مؤسسة واحدة.
     *
     * @param Builder<StudentProfile> $query
     * @return Builder<StudentProfile>
     */
    public function scopeForOrganization(Builder $query, string $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    /**
     * الطلاب المنضمّون فعليًا (تاريخ الانضمام مضى) وغير المؤرشفين.
     *
     * @param Builder<StudentProfile> $query
     * @return Builder<StudentProfile>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNotNull('joined_at')->where('joined_at', '<=', now()->toDateString());
    }
}
