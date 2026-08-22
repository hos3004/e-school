<?php

declare(strict_types=1);

namespace Modules\Assessments\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

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
    ];

    protected function casts(): array
    {
        return [
            'body' => 'array',
            'options' => 'array',
            'correct_answer' => 'array',
            'score' => 'int',
            'sort_order' => 'int',
        ];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order');
    }
}
