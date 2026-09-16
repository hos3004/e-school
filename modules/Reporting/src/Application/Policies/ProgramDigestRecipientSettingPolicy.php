<?php

declare(strict_types=1);

namespace Modules\Reporting\Application\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Reporting\Domain\Models\ProgramDigestRecipientSetting;

/** سياسة إعداد مستلم التقرير الشهري المجمَّع للبرامج — صلاحية واحدة للقراءة والتعديل. */
final class ProgramDigestRecipientSettingPolicy
{
    /** @param Authenticatable&object{organization_id: string} $user */
    public function view(Authenticatable $user): bool
    {
        return $user->can('reporting.settings.manage');
    }

    /** @param Authenticatable&object{organization_id: string} $user */
    public function update(Authenticatable $user, ProgramDigestRecipientSetting $setting): bool
    {
        return $user->can('reporting.settings.manage')
            && $setting->organization_id === $user->organization_id;
    }

    /** @param Authenticatable&object{organization_id: string} $user */
    public function create(Authenticatable $user): bool
    {
        return $user->can('reporting.settings.manage');
    }
}
