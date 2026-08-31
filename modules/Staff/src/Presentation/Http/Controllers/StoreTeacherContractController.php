<?php

declare(strict_types=1);

namespace Modules\Staff\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\Staff\Application\Actions\CreateTeacherContract;
use Modules\Staff\Domain\Enums\ContractBasis;
use Modules\Staff\Domain\Models\StaffProfile;
use Modules\Staff\Presentation\Http\Requests\StoreTeacherContractRequest;
use Modules\Staff\Presentation\Http\Resources\TeacherContractResource;
use Shared\ValueObjects\Money;
use Symfony\Component\HttpFoundation\Response;

final class StoreTeacherContractController
{
    public function __invoke(StoreTeacherContractRequest $request, CreateTeacherContract $action): JsonResponse
    {
        $validated = $request->validated();

        /** @var StaffProfile $profile */
        $profile = StaffProfile::query()->findOrFail($validated['staff_profile_id']);

        $baseAmount = isset($validated['base_amount_major'])
            ? Money::fromMajor((string) $validated['base_amount_major'], (string) config('staff.currency.default', 'EGP'))
            : null;

        $contract = $action->execute(
            profile: $profile,
            basis: ContractBasis::from($validated['basis']),
            effectiveFrom: $validated['effective_from'],
            effectiveTo: $validated['effective_to'] ?? null,
            baseAmount: $baseAmount,
            monthlyTargetSessions: $validated['monthly_target_sessions'] ?? null,
            targetAdminTasks: $validated['target_admin_tasks'] ?? null,
            targetTrainingSessions: $validated['target_training_sessions'] ?? null,
            terms: $validated['terms'] ?? null,
            actorId: auth()->id() === null ? null : (string) auth()->id(),
            reason: isset($validated['reason']) ? (string) $validated['reason'] : null,
        );

        return new TeacherContractResource($contract)->response()->setStatusCode(Response::HTTP_CREATED);
    }
}
