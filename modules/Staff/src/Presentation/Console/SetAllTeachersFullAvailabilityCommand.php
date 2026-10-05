<?php

declare(strict_types=1);

namespace Modules\Staff\Presentation\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Modules\Staff\Application\Actions\SetAllTeachersFullAvailability;
use Modules\Staff\Domain\Models\StaffProfile;
use Modules\Staff\Domain\Models\TeacherAvailability;

/**
 * إتاحة 24/7 لكل معلم عامل في مؤسسة — يعاين افتراضيًا ولا يكتب إلا مع --apply.
 */
final class SetAllTeachersFullAvailabilityCommand extends Command
{
    protected $signature = 'staff:set-all-teachers-full-availability
        {--organization= : Organization ULID}
        {--reason= : Written reason recorded in the audit log}
        {--actor= : Acting user ULID; omitted runs are recorded as system}
        {--timezone= : Timezone for the declared windows; defaults to the application timezone}
        {--apply : Apply the change; otherwise only report what would change}';

    protected $description = 'Replace every active teacher availability window in one organization with a full 24/7 week.';

    public function handle(SetAllTeachersFullAvailability $action): int
    {
        $organizationId = $this->option('organization');
        if (!is_string($organizationId) || !Str::isUlid($organizationId)) {
            $this->error(__('staff::availability.organization_required'));

            return self::FAILURE;
        }

        $reason = trim((string) $this->option('reason'));
        if ($reason === '') {
            $this->error(__('staff::availability.full_week_reason_required'));

            return self::FAILURE;
        }

        $actorId = $this->option('actor');
        if ($actorId !== null && !Str::isUlid((string) $actorId)) {
            $this->error(__('staff::availability.actor_invalid'));

            return self::FAILURE;
        }

        $teacherIds = StaffProfile::query()->forOrganization($organizationId)->active()->pluck('id');

        if (!$this->option('apply')) {
            $this->info(__('staff::availability.full_week_preview', [
                'teachers' => $teacherIds->count(),
                'slots' => $teacherIds->count() * 7,
                'removed' => TeacherAvailability::query()->whereIn('staff_profile_id', $teacherIds)->count(),
            ]));

            return self::SUCCESS;
        }

        $result = $action->execute(
            $organizationId,
            $reason,
            $actorId === null ? null : (string) $actorId,
            ($timezone = $this->option('timezone')) === null ? null : (string) $timezone,
        );

        $this->info(__('staff::availability.full_week_applied', [
            'teachers' => $result['teachers'],
            'slots' => $result['slots'],
            'removed' => $result['removed'],
        ]));

        foreach ($result['skipped'] as $failure) {
            $this->warn($failure['staff_profile_id'].' — '.$failure['error']);
        }

        return $result['skipped'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
