<?php

declare(strict_types=1);

namespace Modules\Scheduling\Application\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Scheduling\Domain\Models\ScheduleChangeRequest;

/**
 * صلاحيات طلب تغيير الموعد الدائم.
 *
 * `schedule.change.request` صلاحية المعلم لاقتراح موعد جديد لجدوله؛ الانتماء
 * للجدول نفسه يفرضه RequestScheduleChange بمقارنة staff_profile_id.
 * `schedule.change.respond` صلاحية الطالب للرد؛ ارتباطه بالطلب يفرضه صف القبول.
 */
final class ScheduleChangeRequestPolicy
{
    public function viewAny(Authenticatable $user): bool
    {
        return $user->can('schedule.view');
    }

    public function view(Authenticatable $user, ScheduleChangeRequest $request): bool
    {
        return $user->can('schedule.view') && $this->sameOrganization($user, $request);
    }

    public function create(Authenticatable $user): bool
    {
        return $user->can('schedule.change.request');
    }

    public function respond(Authenticatable $user, ScheduleChangeRequest $request): bool
    {
        return $user->can('schedule.change.respond') && $this->sameOrganization($user, $request);
    }

    public function withdraw(Authenticatable $user, ScheduleChangeRequest $request): bool
    {
        return ($user->can('schedule.change.request') || $user->can('schedule.manage'))
            && $this->sameOrganization($user, $request);
    }

    private function sameOrganization(Authenticatable $user, ScheduleChangeRequest $request): bool
    {
        return (string) $request->organization_id === (string) $user->getAttribute('organization_id');
    }
}
