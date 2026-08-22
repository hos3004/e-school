<?php

declare(strict_types=1);

namespace Modules\Organization\Application\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Modules\Organization\Domain\Events\OrganizationSettingUpdated;
use Modules\Organization\Domain\Models\Organization;
use Modules\Organization\Domain\Models\OrganizationSetting;
use Shared\Support\BusinessRuleViolation;

/**
 * إنشاء أو تحديث إعداد على مستوى المؤسسة.
 *
 * المفتوح للكتابة مفتاح واحد بقيمة واحدة؛ القيمة تُخزَّن jsonb فتستوعب
 * سلسلة أو عددًا أو بنية مصفوفية بسيطة.
 */
final readonly class UpsertOrganizationSetting
{
    /**
     * @param  string|int|float|bool|array<string, mixed>|null  $value
     */
    public function execute(Organization $organization, string $key, mixed $value): OrganizationSetting
    {
        if (mb_strlen($key) > (int) config('organization.limits.setting_key_max_length')) {
            throw BusinessRuleViolation::make(
                'organization.setting_key_too_long',
                'organization::errors.setting_key_too_long',
                ['key' => Str::limit($key, 32)],
            );
        }

        /** @var OrganizationSetting $setting */
        $setting = DB::transaction(function () use ($organization, $key, $value): OrganizationSetting {
            return OrganizationSetting::query()->updateOrCreate(
                [
                    'organization_id' => $organization->id,
                    'key' => $key,
                ],
                [
                    'value' => $value,
                    'updated_by' => auth()->id(),
                ],
            );
        });

        Event::dispatch(new OrganizationSettingUpdated(
            organizationId: $organization->id,
            key: $key,
            value: $value,
        ));

        return $setting;
    }
}
