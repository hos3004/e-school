<?php

declare(strict_types=1);

namespace Modules\Staff\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Shared\Concerns\HasUlid;

/**
 * تأهيل المعلم لكورس معين.
 *
 * @property string $id
 * @property string $staff_profile_id
 * @property string $course_id
 * @property CarbonImmutable $qualified_at
 * @property string|null $qualified_by
 * @property string|null $notes
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
final class TeacherCourse extends Model
{
    use HasUlid;

    protected $table = 'teacher_courses';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'staff_profile_id',
        'course_id',
        'qualified_at',
        'qualified_by',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'qualified_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
