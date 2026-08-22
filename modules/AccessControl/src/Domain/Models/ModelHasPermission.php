<?php

declare(strict_types=1);

namespace Modules\AccessControl\Domain\Models;

use Shared\Concerns\HasModuleFactory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * صلاحية مباشرة لنموذج دون وسيط دور — تُمنع إلا للاستثناءات المحدودة.
 *
 * الكتابة عليه تتم حصرًا عبر GrantModelPermissionAction /
 * RevokeModelPermissionAction.
 */
final class ModelHasPermission extends Model
{
    use HasModuleFactory;

    public $timestamps = false;

    protected $primaryKey = null;

    protected $table = 'model_has_permissions';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'permission_id',
        'model_type',
        'model_id',
    ];

    /**
     * @return BelongsTo<Permission, $this>
     */
    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class, 'permission_id');
    }
}
