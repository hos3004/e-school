<?php

declare(strict_types=1);

namespace Modules\Students\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Modules\Identity\Domain\Contracts\DTOs\CreateUserAccountData;
use Modules\Identity\Domain\Contracts\UserAccountProvisioner;
use Modules\Students\Application\Actions\CreateRegistrationApplicationAction;
use Modules\Students\Application\Actions\SubmitRegistrationApplicationAction;
use Modules\Students\Presentation\Http\Requests\PublicRegistrationRequest;
use Shared\Support\Transaction;

final class PublicRegistrationController extends Controller
{
    public function __construct(
        private readonly UserAccountProvisioner $accounts,
        private readonly CreateRegistrationApplicationAction $createAction,
        private readonly SubmitRegistrationApplicationAction $submitAction,
        private readonly Transaction $transaction,
    ) {}

    public function __invoke(PublicRegistrationRequest $request, string $organizationId): JsonResponse
    {
        $validated = $request->validated();

        $application = $this->transaction->run(function () use ($request, $validated, $organizationId) {
            $authenticatedUserId = $request->user()?->getAuthIdentifier();

            if ($authenticatedUserId !== null) {
                $account = $this->accounts->confirmExistingAccount(
                    organizationId: $organizationId,
                    userId: (string) $authenticatedUserId,
                    email: $validated['email'] ?? null,
                    phone: $validated['phone'] ?? null,
                );
            } else {
                $account = $this->accounts->create(new CreateUserAccountData(
                    organizationId: $organizationId,
                    name: (string) $validated['full_name'],
                    email: $validated['email'] ?? null,
                    username: $this->makeUsername(),
                    phone: $validated['phone'] ?? null,
                    password: Str::password((int) config('admission.account.generated_password_length')),
                    locale: (string) config('app.locale'),
                    timezone: (string) config('app.timezone'),
                ));
            }

            $application = $this->createAction->execute(
                data: $validated,
                organizationId: $organizationId,
                userId: $account->id,
            );

            return $this->submitAction->execute($application);
        });

        return response()->json([
            'message' => __('students::messages.registration_submitted_successfully'),
            'application_id' => (string) $application->getKey(),
            'status' => $application->status->value,
            'submitted_at' => $application->submitted_at?->toIso8601String(),
        ], 201);
    }

    private function makeUsername(): string
    {
        $suffix = mb_strtolower(substr((string) Str::ulid(), -10));
        $separator = (string) config('admission.username.separator', '.');
        $maxLength = (int) config('admission.username.max_length', 32);
        $maxPrefixLength = max(1, $maxLength - strlen($separator) - strlen($suffix));
        $prefix = substr((string) config('admission.username.fallback_prefix', 'student'), 0, $maxPrefixLength);

        return mb_strtolower($prefix.$separator.$suffix);
    }
}
