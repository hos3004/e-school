<?php

declare(strict_types=1);

namespace Modules\Students\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
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
 */
final class StudentProfile extends Model
{
    /** @use HasFactory<StudentProfileFactory> */
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
     * حصر الاستعلام على مؤسسة واحدة.
     */
    public function scopeForOrganization(Builder $query, string $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    /**
     * الطلاب المنضمّون فعليًا (تاريخ الانضمام مضى) وغير المؤرشفين.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNotNull('joined_at')->where('joined_at', '<=', now()->toDateString());
    }
}
