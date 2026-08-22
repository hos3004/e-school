<?php

declare(strict_types=1);

namespace Modules\Certificates\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

final class CertificateTemplate extends Model
{
    use HasModuleFactory;
    use HasUlid;
    use SoftDeletes;

    protected $table = 'certificate_templates';

    protected $fillable = [
        'organization_id',
        'program_id',
        'name',
        'layout',
        'background_image_path',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'name' => 'array',
            'layout' => 'array',
            'is_active' => 'bool',
        ];
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForOrganization(Builder $query, string $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }
}
