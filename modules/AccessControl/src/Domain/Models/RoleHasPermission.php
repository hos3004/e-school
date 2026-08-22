<?php

declare(strict_types=1);

namespace Modules\AccessControl\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Shared\Concerns\HasModuleFactory;

/**
 * جدول الربط بين الأدوار والصلاحيات — مفتاح مركّب بلا معرّف خاص.
 *
 * الكتابة عليه تتم حصرًا عبر SyncRolePermissionsAction داخل المعاملة.
 */
final class RoleHasPermission extends Model
{
    use HasModuleFactory;

    public $timestamps = false;

    protected $primaryKey = null;

    protected $table = 'role_has_permissions';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'role_id',
        'permission_id',
    ];

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    /**
     * @return BelongsTo<Permission, $this>
     */
    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class, 'permission_id');
    }
}
