<?php

declare(strict_types=1);

namespace Modules\Certificates\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

final class Certificate extends Model
{
    use HasModuleFactory;
    use HasUlid;
    use SoftDeletes;

    protected $table = 'certificates';

    protected $fillable = [
        'organization_id',
        'certificate_template_id',
        'student_profile_id',
        'program_id',
        'enrollment_id',
        'serial_number',
        'title',
        'issued_at',
        'issued_by',
        'expires_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'title' => 'array',
            'issued_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    public function scopeForOrganization(Builder $query, string $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->whereNotNull('expires_at')->where('expires_at', '<=', now());
    }
}
