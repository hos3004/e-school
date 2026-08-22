<?php

declare(strict_types=1);

namespace Modules\Organization\Application\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Modules\Organization\Domain\Events\OrganizationUpdated;
use Modules\Organization\Domain\Models\Organization;

/**
 * تعديل بيانات مؤسسة قائمة.
 */
final readonly class UpdateOrganization
{
    /**
     * @param  array<string, mixed>  $attributes  الحقول المسموح تعديلها فقط
     * @param  list<string>  $changedFields  تُملأ تلقائيًا من diff القيم
     */
    public function execute(Organization $organization, array $attributes): Organization
    {
        /** @var list<string> $changed */
        $changed = [];

        /** @var Organization $organization */
        $organization = DB::transaction(function () use ($organization, $attributes, &$changed): Organization {
            foreach ($attributes as $key => $value) {
                if (array_key_exists($key, $organization->getAttributes())
                    && $organization->getAttribute($key) === $value) {
                    continue;
                }
                $changed[] = $key;
            }

            if ($changed !== []) {
                $organization->fill($attributes)->save();
            }

            return $organization;
        });

        if ($changed !== []) {
            Event::dispatch(new OrganizationUpdated(
                organizationId: $organization->id,
                changedFields: $changed,
            ));
        }

        return $organization;
    }
}
