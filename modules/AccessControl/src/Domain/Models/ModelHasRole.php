<?php

declare(strict_types=1);

namespace Modules\AccessControl\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Shared\Concerns\HasModuleFactory;

/**
 * إسناد دور لنموذج (مستخدم/ملف شخصي) عبر morph map.
 *
 * model_type يُخزَّن كاسم مستقر في الـ morph map وليس FQCN، والكتابة
 * عليه تتم حصرًا عبر AssignRoleAction / RevokeRoleAction.
 */
final class ModelHasRole extends Model
{
    use HasModuleFactory;

    public $timestamps = false;

    protected $primaryKey = null;

    protected $table = 'model_has_roles';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'role_id',
        'model_type',
        'model_id',
    ];

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }
}
