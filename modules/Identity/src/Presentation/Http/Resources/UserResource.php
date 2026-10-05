<?php

declare(strict_types=1);

namespace Modules\Identity\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\AccessControl\Domain\Contracts\AccessControlQuerier;
use Modules\AccessControl\Domain\ValueObjects\RoleData;
use Modules\Identity\Domain\Models\User;

/**
 * @property User $resource
 */
final class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;

        $roles = app(AccessControlQuerier::class)->rolesForModel(
            $user->getMorphClass(),
            (string) $user->getAuthIdentifier(),
        );

        return [
            'id' => $user->id,
            'organization_id' => $user->organization_id,
            'name' => $user->name,
            'email' => $user->email,
            'username' => $user->username,
            'phone' => $user->phone,
            'locale' => $user->locale,
            'timezone' => $user->timezone,
            'avatar_path' => $user->avatar_path,
            'status' => $user->status->value,
            // أسماء الأدوار فقط — تكفي عميل الموبايل ليحدد الشاشة الرئيسية
            // المناسبة (معلم/طالب/ولي أمر) بلا حاجة لطلب صلاحيات مفصّلة.
            'roles' => array_map(static fn (RoleData $role): string => $role->name, $roles),
            'email_verified' => $user->hasVerifiedEmail(),
            'last_login_at' => $user->last_login_at?->toIso8601String(),
            'created_at' => $user->created_at?->toIso8601String(),
        ];
    }
}
