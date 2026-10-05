<?php

declare(strict_types=1);

namespace Modules\Payroll\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Payroll\Application\Actions\ReleaseDeferredEntriesAction;
use Modules\Payroll\Domain\Models\PayrollEntry;
use Modules\Payroll\Presentation\Http\Requests\ReleaseDeferredEntriesRequest;

/**
 * تحرير القيود المؤجَّلة بعد إقامة حصة التلافي.
 */
final class ReleaseDeferredEntriesController extends Controller
{
    public function __construct(
        private readonly ReleaseDeferredEntriesAction $action,
    ) {}

    public function __invoke(ReleaseDeferredEntriesRequest $request, string $makeupSession): JsonResponse
    {
        Gate::authorize('release', PayrollEntry::class);

        $this->action->execute(
            organizationId: (string) $request->user()?->getAttribute('organization_id'),
            makeupSessionId: $makeupSession,
            staffProfileId: (string) $request->validated('staff_profile_id'),
            actorId: (string) $request->user()->getAuthIdentifier(),
            reason: (string) $request->validated('reason'),
        );

        return response()->json([
            'message' => __('payroll::messages.deferred_released'),
        ]);
    }
}
