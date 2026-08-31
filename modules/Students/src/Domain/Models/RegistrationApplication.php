<?php

declare(strict_types=1);

namespace Modules\Students\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Students\Domain\Enums\RegistrationStatus;
use Modules\Students\Domain\Enums\StudentGender;
use Shared\Concerns\HasUlid;

/**
 * طلب تسجيل طالب جديد.
 *
 * @property string $id
 * @property string $organization_id
 * @property string|null $registration_form_id
 * @property string|null $user_id
 * @property string|null $student_profile_id
 * @property RegistrationStatus $status
 * @property string $full_name
 * @property CarbonImmutable $date_of_birth
 * @property StudentGender $gender
 * @property string $country_id
 * @property string $region_id
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $preferred_program_id
 * @property string|null $preferred_course_id
 * @property string|null $notes
 * @property list<array{question_id: string, question: string, type?: string, answer?: string|list<string>}>|null $evaluation_answers
 * @property CarbonImmutable|null $submitted_at
 * @property string|null $reviewed_by
 * @property CarbonImmutable|null $reviewed_at
 * @property string|null $decision_reason
 * @property string|null $duplicate_of_application_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 */
final class RegistrationApplication extends Model
{
    use HasUlid;
    use SoftDeletes;

    protected $table = 'registration_applications';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'registration_form_id',
        'user_id',
        'student_profile_id',
        'status',
        'full_name',
        'date_of_birth',
        'gender',
        'country_id',
        'region_id',
        'email',
        'phone',
        'preferred_program_id',
        'preferred_course_id',
        'notes',
        'evaluation_answers',
        'submitted_at',
        'reviewed_by',
        'reviewed_at',
        'decision_reason',
        'duplicate_of_application_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RegistrationStatus::class,
            'gender' => StudentGender::class,
            'evaluation_answers' => 'array',
            'date_of_birth' => 'immutable_date',
            'submitted_at' => 'immutable_datetime',
            'reviewed_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
            'deleted_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<StudentProfile, $this>
     */
    public function studentProfile(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class, 'student_profile_id');
    }

    /** @return BelongsTo<RegistrationForm, $this> */
    public function registrationForm(): BelongsTo
    {
        return $this->belongsTo(RegistrationForm::class);
    }

    /**
     * @param Builder<RegistrationApplication> $query
     * @return Builder<RegistrationApplication>
     */
    public function scopeForOrganization(Builder $query, string $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    /**
     * @param Builder<RegistrationApplication> $query
     * @return Builder<RegistrationApplication>
     */
    public function scopeSearch(Builder $query, string $search): Builder
    {
        $search = trim($search);

        return $query->where(function (Builder $nested) use ($search): void {
            $nested
                ->where('full_name', 'ilike', "%{$search}%")
                ->orWhere('id', 'ilike', "%{$search}%")
                ->orWhere('email', 'ilike', "%{$search}%")
                ->orWhere('phone', 'ilike', "%{$search}%");
        });
    }
}
