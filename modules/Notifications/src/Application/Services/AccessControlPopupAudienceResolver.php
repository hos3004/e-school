<?php

declare(strict_types=1);

namespace Modules\Notifications\Application\Services;

use Modules\AccessControl\Domain\Contracts\AccessControlQuerier;
use Modules\Notifications\Domain\Contracts\PopupAudienceResolver;

/**
 * حلّ جمهور المستخدم عبر أدوار AccessControl المعلنة — العقد القانوني الوحيد.
 * «الجميع» (AllAuthenticated) ليس دورًا يُحلّ هنا؛ إنه مطابقة سلبية تُفحص
 * في طبقة الأهلية لأي مستخدم مصادق.
 */
final readonly class AccessControlPopupAudienceResolver implements PopupAudienceResolver
{
    /** خريطة اسم الدور النظامي ← جمهور الحملة. */
    private const ROLE_AUDIENCE_MAP = [
        'student' => 'student',
        'guardian' => 'guardian',
        'teacher' => 'teacher',
        'academic_supervisor' => 'supervisor',
        'registrar' => 'supervisor',
        'platform_admin' => 'administrator',
    ];

    public function __construct(private AccessControlQuerier $accessControl) {}

    public function audiencesFor(string $modelType, string $userId): array
    {
        $audiences = [];

        foreach ($this->accessControl->rolesForModel($modelType, $userId) as $role) {
            $audience = self::ROLE_AUDIENCE_MAP[$role->name] ?? null;

            if ($audience !== null && !in_array($audience, $audiences, true)) {
                $audiences[] = $audience;
            }
        }

        return $audiences;
    }
}
