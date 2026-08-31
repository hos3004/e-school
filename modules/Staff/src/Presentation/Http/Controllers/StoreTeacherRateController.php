<?php

declare(strict_types=1);

namespace Modules\Staff\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\Staff\Application\Actions\AddTeacherRate;
use Modules\Staff\Domain\Enums\RateScope;
use Modules\Staff\Domain\Models\TeacherContract;
use Modules\Staff\Presentation\Http\Requests\StoreTeacherRateRequest;
use Modules\Staff\Presentation\Http\Resources\TeacherRateResource;
use Shared\ValueObjects\Money;
use Symfony\Component\HttpFoundation\Response;

final class StoreTeacherRateController
{
    public function __invoke(StoreTeacherRateRequest $request, TeacherContract $contract, AddTeacherRate $action): JsonResponse
    {
        $validated = $request->validated();

        $rate = $action->execute(
            contract: $contract,
            scope: RateScope::from($validated['scope']),
            amount: Money::fromMajor((string) $validated['amount_major'], $contract->currency ?? (string) config('staff.currency.default', 'EGP')),
            effectiveFrom: $validated['effective_from'],
            effectiveTo: $validated['effective_to'] ?? null,
            programId: $validated['program_id'] ?? null,
            courseId: $validated['course_id'] ?? null,
            sessionType: $validated['session_type'] ?? null,
            actorId: auth()->id() === null ? null : (string) auth()->id(),
            reason: isset($validated['reason']) ? (string) $validated['reason'] : null,
        );

        return new TeacherRateResource($rate)->response()->setStatusCode(Response::HTTP_CREATED);
    }
}
