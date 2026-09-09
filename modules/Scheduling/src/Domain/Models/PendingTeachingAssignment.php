<?php

declare(strict_types=1);

namespace Modules\Scheduling\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Shared\Concerns\HasUlid;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $student_profile_id
 * @property string $staff_profile_id
 * @property string $course_id
 * @property string $session_type
 * @property int $duration_minutes
 */
final class PendingTeachingAssignment extends Model
{
    use HasUlid, SoftDeletes;

    protected $fillable = ['organization_id', 'student_profile_id', 'staff_profile_id', 'course_id', 'session_type', 'duration_minutes', 'reason', 'created_by'];

    protected function casts(): array
    {
        return ['duration_minutes' => 'integer'];
    }
}
