<?php

declare(strict_types=1);

namespace Modules\Academics\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Academics\Domain\Enums\TargetGender;

/**
 * @property string $id
 * @property string $program_id
 * @property list<string> $countries
 * @property list<string> $regions
 * @property int|null $age_from
 * @property int|null $age_to
 * @property TargetGender|null $gender
 * @property bool $manual_approval_required
 * @property string $teacher_gender_rule
 * @property bool $requires_individual_sessions
 * @property-read Program|null $program
 */
final class ProgramEligibility extends Model
{
    use HasUlids;

    protected $table = 'program_eligibility';

    protected $fillable = [
        'program_id',
        'countries',
        'regions',
        'age_from',
        'age_to',
        'gender',
        'manual_approval_required',
        'teacher_gender_rule',
        'requires_individual_sessions',
    ];

    protected function casts(): array
    {
        return [
            'countries' => 'array',
            'regions' => 'array',
            'age_from' => 'integer',
            'age_to' => 'integer',
            'gender' => TargetGender::class,
            'manual_approval_required' => 'boolean',
            'requires_individual_sessions' => 'boolean',
        ];
    }

    /** @return BelongsTo<Program, $this> */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class, 'program_id');
    }
}
