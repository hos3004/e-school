<?php

declare(strict_types=1);

namespace Modules\Staff\Presentation\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Modules\Staff\Application\Actions\ActivatePendingTeacherAvailability;
use Modules\Staff\Domain\Enums\TeacherAvailabilityApprovalStatus;
use Modules\Staff\Domain\Models\StaffProfile;
use Modules\Staff\Domain\Models\TeacherAvailability;

final class ActivatePendingAvailabilityCommand extends Command
{
    protected $signature = 'staff:activate-pending-availability {--organization= : Organization ULID} {--apply : Apply the policy; otherwise only count eligible rows}';

    protected $description = 'Activate pending teacher availability for one organization under the immediate availability policy.';

    public function handle(ActivatePendingTeacherAvailability $activate): int
    {
        $organizationId = $this->option('organization');
        if (!is_string($organizationId) || !Str::isUlid($organizationId)) {
            $this->error(__('staff::availability.organization_required'));

            return self::FAILURE;
        }

        if ((bool) config('scheduling.availability.teacher_requires_approval')) {
            $this->error(__('staff::availability.approval_enabled'));

            return self::FAILURE;
        }

        $query = TeacherAvailability::query()
            ->whereIn('staff_profile_id', StaffProfile::query()->forOrganization($organizationId)->select('id'))
            ->where('approval_status', TeacherAvailabilityApprovalStatus::Pending);

        if (!$this->option('apply')) {
            $this->info(__('staff::availability.preview', ['count' => $query->count()]));

            return self::SUCCESS;
        }

        $count = 0;
        foreach ($query->orderBy('id')->lazyById() as $slot) {
            if ($activate->execute($organizationId, (string) $slot->id)) {
                $count++;
            }
        }
        $this->info(__('staff::availability.activated', ['count' => $count]));

        return self::SUCCESS;
    }
}
