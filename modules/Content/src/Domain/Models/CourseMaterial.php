<?php

declare(strict_types=1);

namespace Modules\Content\Domain\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Content\Domain\Enums\MaterialType;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

/**
 * مادة تعليمية مرتبطة بكورس — ملف مرفوع أو رابط خارجي.
 *
 * المادة تُخفى تلقائيًا عن الطلاب خارج نافذة الظهور visible_from/visible_to،
 * ولا تُحذف فعليًا أبدًا (تعليق فقط).
 */
final class CourseMaterial extends Model
{
    use HasModuleFactory;
    use HasUlid;
    use SoftDeletes;

    /** الجدول بلا عمود updated_at. */
    public const UPDATED_AT = null;

    protected $table = 'course_materials';

    protected $fillable = [
        'course_id',
        'title',
        'type',
        'disk',
        'path',
        'external_url',
        'size_bytes',
        'visible_from',
        'visible_to',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => MaterialType::class,
            'title' => 'array',
            'size_bytes' => 'int',
            'visible_from' => 'immutable_datetime',
            'visible_to' => 'immutable_datetime',
        ];
    }

    /**
     * مواد كورس محدد.
     */
    public function scopeForCourse(Builder $query, string $courseId): Builder
    {
        return $query->where('course_id', $courseId);
    }

    /**
     * مواد ظاهرة الآن ضمن نافذة الظهور (أو بلا نافذة).
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->where(fn (Builder $q): Builder => $q->whereNull('visible_from')->orWhere('visible_from', '<=', now()))
            ->where(fn (Builder $q): Builder => $q->whereNull('visible_to')->orWhere('visible_to', '>', now()));
    }

    /**
     * مواد من نوع محدد.
     */
    public function scopeOfType(Builder $query, MaterialType $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * هل المادة ظاهرة الآن للطلاب؟
     */
    public function isCurrentlyVisible(?CarbonInterface $at = null): bool
    {
        $moment = $at ?? now();

        if ($this->visible_from !== null && $this->visible_from->greaterThan($moment)) {
            return false;
        }

        if ($this->visible_to !== null && $this->visible_to->lessThanOrEqualTo($moment)) {
            return false;
        }

        return true;
    }
}
