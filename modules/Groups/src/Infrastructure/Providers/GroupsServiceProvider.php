<?php

declare(strict_types=1);

namespace Modules\Groups\Infrastructure\Providers;

use Modules\Groups\Application\Policies\GroupMembershipPolicy;
use Modules\Groups\Application\Policies\GroupPolicy;
use Modules\Groups\Application\Policies\GroupProgramPolicy;
use Modules\Groups\Application\Policies\GroupTeacherPolicy;
use Modules\Groups\Domain\Models\Group;
use Modules\Groups\Domain\Models\GroupMembership;
use Modules\Groups\Domain\Models\GroupProgram;
use Modules\Groups\Domain\Models\GroupTeacher;
use Shared\Module\BaseModuleServiceProvider;
use Shared\Support\Transaction;

final class GroupsServiceProvider extends BaseModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'Groups';
    }

    /**
     * ربط Domain Events بمستمعيها داخل هذا الموديول — لا مستمعين داخليين
     * حتى الآن؛ الأحداث تُستهلك من الموديولات الأخرى.
     *
     * @return array<class-string, list<class-string>>
     */
    protected function listeners(): array
    {
        return [];
    }

    /**
     * ربط الموارد بسياسات الصلاحيات.
     *
     * @return array<class-string, class-string>
     */
    protected function policies(): array
    {
        return [
            Group::class => GroupPolicy::class,
            GroupMembership::class => GroupMembershipPolicy::class,
            GroupTeacher::class => GroupTeacherPolicy::class,
            GroupProgram::class => GroupProgramPolicy::class,
        ];
    }

    /**
     * ربط الـ Contracts بتنفيذاتها — ربط Transaction::class مشترك عالميًا
     * في AppServiceProvider، ولا عقود خاصة بهذا الموديول بعد.
     *
     * الإجراءات لا تُسجَّل كأحادية حتى تستلم كل استدعاء الـ Dispatcher الحالي
     * (وهو ما يجعل Event::fake يعمل في الاختبارات).
     *
     * @return array<class-string, class-string>
     */
    protected function bindings(): array
    {
        return [];
    }
}
