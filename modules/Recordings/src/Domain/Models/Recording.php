<?php

declare(strict_types=1);

namespace Modules\Recordings\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Recordings\Domain\Enums\RecordingStatus;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

/**
 * تسجيل حصة — يملكه موديول Recordings.
 *
 * session_id و classroom_id معرّفات خارجية فقط: لا علاقات Eloquent
 * نحو نماذج موديولات أخرى. الملف يُحتفظ به مدة config('recordings.retention_days')
 * يومًا من available_from ثم يُؤرشف ويُحذف وفق config('recordings.on_expiry').
 */
final class Recording extends Model
{
    use HasModuleFactory;
    use HasUlid;
    use SoftDeletes;

    protected $table = 'recordings';

    protected $fillable = [
        'organization_id',
        'session_id',
        'classroom_id',
        'provider',
        'external_recording_id',
        'status',
        'duration_seconds',
        'size_bytes',
        'disk',
        'path',
        'thumbnail_path',
        'archive_driver',
        'archive_path',
        'archived_at',
        'available_from',
        'expires_at',
        'deleted_by',
        'deletion_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => RecordingStatus::class,
            'duration_seconds' => 'int',
            'size_bytes' => 'int',
            'archived_at' => 'immutable_datetime',
            'available_from' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
        ];
    }

    public function scopeForOrganization(Builder $query, string $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    public function scopeForSession(Builder $query, string $sessionId): Builder
    {
        return $query->where('session_id', $sessionId);
    }

    /** التسجيلات القابلة للمشاهدة الآن: غير محذوفة وبحالة Ready. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', RecordingStatus::Ready);
    }

    /** ما زال داخل مدة الاحتفاظ — لم يُؤرشف أو ينتهِ. */
    public function scopeWithinRetention(Builder $query): Builder
    {
        return $query->whereIn('status', [RecordingStatus::Processing, RecordingStatus::Ready]);
    }

    /** تجاوز موعد انتهاء الاحتفاظ وينتظر المعالجة (أرشفة أو حذف). */
    public function scopePastRetention(Builder $query, ?CarbonImmutable $at = null): Builder
    {
        return $query
            ->withinRetention()
            ->where('expires_at', '<=', $at ?? CarbonImmutable::now('UTC'));
    }

    public function scopeWithStatus(Builder $query, RecordingStatus $status): Builder
    {
        return $query->where('status', $status);
    }
}
