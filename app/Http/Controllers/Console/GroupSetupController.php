<?php

declare(strict_types=1);

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Http\Requests\Console\GroupSetupRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Modules\Groups\Application\Services\ConsoleSetupService;
use Shared\Support\BusinessRuleViolation;

final class GroupSetupController extends Controller
{
    public function __construct(private readonly ConsoleSetupService $groups) {}

    public function save(GroupSetupRequest $request, ?string $group = null): RedirectResponse
    {
        return $this->perform($request, function (string $organizationId, string $actorId) use ($request, $group): string {
            return $this->groups->save($organizationId, $group, $request->actionData(), $actorId,
                __('console_courses.audit.'.($group === null ? 'create_groups' : 'update_groups')));
        });
    }

    public function assign(GroupSetupRequest $request, string $group): RedirectResponse
    {
        return $this->perform($request, function (string $organizationId, string $actorId) use ($request, $group): string {
            $this->groups->assign($organizationId, $group, $request->actionData(), $actorId, __('console_courses.audit.assign_teacher'));

            return $group;
        });
    }

    public function activate(GroupSetupRequest $request, string $group): RedirectResponse
    {
        return $this->perform($request, function (string $organizationId, string $actorId) use ($group): string {
            $this->groups->activate($organizationId, $group, $actorId, __('console_courses.audit.activate_group'));

            return $group;
        });
    }

    /** @param \Closure(string, string): string $action */
    private function perform(GroupSetupRequest $request, \Closure $action): RedirectResponse
    {
        $organizationId = (string) $request->user()?->organization_id;
        abort_if($organizationId === '', 403);
        try {
            $id = $action($organizationId, (string) $request->user()?->getAuthIdentifier());
        } catch (BusinessRuleViolation $exception) {
            throw ValidationException::withMessages(['form' => $exception->getMessage()]);
        }

        return redirect()->route('console.groups.index', ['focus' => $id])->with('success', __('console_courses.saved'));
    }
}
