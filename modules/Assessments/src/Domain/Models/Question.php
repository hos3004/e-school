<?php

declare(strict_types=1);

namespace Modules\Assessments\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Assessments\Domain\Enums\QuestionType;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

/**
 * @property string $id
 * @property string $assessment_id
 * @property QuestionType $type
 * @property array<string, mixed> $body
 * @property array<int, array<string, mixed>>|null $options
 * @property array<string, mixed>|null $correct_answer
 * @property int $score
 * @property int $sort_order
 * @property CarbonImmutable $created_at
 * @property-read Assessment $assessment
 */
final class Question extends Model
{
    use HasModuleFactory;
    use HasUlid;

    public $timestamps = false;

    protected $table = 'questions';

    protected $fillable = [
        'assessment_id',
        'type',
        'body',
        'options',
        'correct_answer',
        'score',
        'sort_order',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => QuestionType::class,
            'body' => 'array',
            'options' => 'array',
            'correct_answer' => 'array',
            'score' => 'int',
            'sort_order' => 'int',
            'created_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Assessment, $this> */
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    /**
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order');
    }
}
