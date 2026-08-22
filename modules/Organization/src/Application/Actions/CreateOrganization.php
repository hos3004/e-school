<?php

declare(strict_types=1);

namespace Modules\Organization\Application\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Modules\Organization\Domain\Events\OrganizationCreated;
use Modules\Organization\Domain\Models\Organization;
use Shared\Support\BusinessRuleViolation;

/**
 * إنشاء مؤسسة جديدة.
 *
 * الترتيب إلزامي: حراس ← DB::transaction ← نشر الأحداث بعد النجاح.
 */
final readonly class CreateOrganization
{
    /**
     * @param array<string, mixed> $attributes قيم مطابقة لـ $fillable في النموذج
     */
    public function execute(array $attributes): Organization
    {
        $slugTaken = Organization::query()->where('slug', $attributes['slug'])->exists();
        if ($slugTaken) {
            throw BusinessRuleViolation::make(
                'organization.slug_taken',
                'organization::errors.slug_taken',
                ['slug' => $attributes['slug']],
            );
        }

        /** @var Organization $organization */
        $organization = DB::transaction(function () use ($attributes): Organization {
            return Organization::query()->create($attributes);
        });

        Event::dispatch(new OrganizationCreated(
            organizationId: $organization->id,
            slug: $organization->slug,
            defaultLocale: $organization->default_locale,
        ));

        return $organization;
    }
}
