<?php

declare(strict_types=1);

namespace Modules\Recordings\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

/**
 * سجل مشاهدة/تنزيل تسجيل — append-only.
 *
 * كل وصول يُسجَّل للتدقيق وفق config('recordings.privacy.log_every_view').
 * user_id معرّف خارجي: لا علاقة لنموذج موديول Identity.
 */
final class RecordingView extends Model
{
    use HasModuleFactory;
    use HasUlid;

    public $timestamps = false;

    protected $table = 'recording_views';

    protected $fillable = [
        'recording_id',
        'user_id',
        'viewed_at',
        'ip_address',
        'user_agent',
        'action',
    ];

    protected function casts(): array
    {
        return [
            'viewed_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Recording, $this>
     */
    public function recording(): BelongsTo
    {
        return $this->belongsTo(Recording::class);
    }

    public function scopeForUser(Builder $query, string $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForRecording(Builder $query, string $recordingId): Builder
    {
        return $query->where('recording_id', $recordingId);
    }
}
