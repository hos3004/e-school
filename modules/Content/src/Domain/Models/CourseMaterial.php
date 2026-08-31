<?php

declare(strict_types=1);

namespace Modules\Content\Domain\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Content\Domain\Enums\MaterialStatus;
use Modules\Content\Domain\Enums\MaterialType;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

/**
 * مادة تعليمية مرتبطة بكورس — ملف مرفوع أو رابط خارجي.
 *
 * المادة تُخفى تلقائيًا عن الطلاب خارج نافذة الظهور visible_from/visible_to،
 * ولا تُحذف فعليًا أبدًا (تعليق فقط).
 *
 * @property string $id
 * @property string $organization_id
 * @property string $course_id
 * @property array<string, string> $title
 * @property array<string, string>|null $description
 * @property MaterialType $type
 * @property MaterialStatus $status
 * @property int $display_order
 * @property int $revision
 * @property string|null $disk
 * @property string|null $path
 * @property string|null $external_url
 * @property int|null $size_bytes
 * @property CarbonImmutable|null $visible_from
 * @property CarbonImmutable|null $visible_to
 * @property CarbonImmutable|null $published_at
 * @property string|null $published_by
 * @property string|null $uploaded_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $deleted_at
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
        'organization_id',
        'course_id',
        'title',
        'description',
        'type',
        'status',
        'display_order',
        'revision',
        'disk',
        'path',
        'external_url',
        'size_bytes',
        'visible_from',
        'visible_to',
        'published_at',
        'published_by',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => MaterialType::class,
            'status' => MaterialStatus::class,
            'title' => 'array',
            'description' => 'array',
            'display_order' => 'int',
            'revision' => 'int',
            'size_bytes' => 'int',
            'visible_from' => 'immutable_datetime',
            'visible_to' => 'immutable_datetime',
            'published_at' => 'immutable_datetime',
        ];
    }

    /** @return HasMany<CourseMaterialVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(CourseMaterialVersion::class, 'material_id')->orderByDesc('revision');
    }

    /**
     * @param Builder<CourseMaterial> $query
     * @return Builder<CourseMaterial>
     */
    public function scopeForOrganization(Builder $query, string $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    /**
     * مواد كورس محدد.
     *
     * @param Builder<CourseMaterial> $query
     * @return Builder<CourseMaterial>
     */
    public function scopeForCourse(Builder $query, string $courseId): Builder
    {
        return $query->where('course_id', $courseId);
    }

    /**
     * مواد ظاهرة الآن ضمن نافذة الظهور (أو بلا نافذة).
     *
     * @param Builder<CourseMaterial> $query
     * @return Builder<CourseMaterial>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->where('status', MaterialStatus::Published)
            ->where(fn (Builder $q): Builder => $q->whereNull('visible_from')->orWhere('visible_from', '<=', now()))
            ->where(fn (Builder $q): Builder => $q->whereNull('visible_to')->orWhere('visible_to', '>', now()));
    }

    /**
     * مواد من نوع محدد.
     *
     * @param Builder<CourseMaterial> $query
     * @return Builder<CourseMaterial>
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
        if (!$this->status->grantsAccess()) {
            return false;
        }

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
