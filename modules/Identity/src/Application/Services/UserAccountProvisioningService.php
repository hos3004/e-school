<?php

declare(strict_types=1);

namespace Modules\Identity\Application\Services;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Modules\Identity\Application\Actions\IssuePhonePasswordResetOtp;
use Modules\Identity\Application\Actions\RegisterUser;
use Modules\Identity\Domain\Contracts\DTOs\CreateUserAccountData;
use Modules\Identity\Domain\Contracts\DTOs\UserAccountData;
use Modules\Identity\Domain\Contracts\UserAccountProvisioner;
use Modules\Identity\Domain\Models\User;
use Shared\Support\BusinessRuleViolation;

final readonly class UserAccountProvisioningService implements UserAccountProvisioner
{
    public function __construct(
        private RegisterUser $registerUser,
    ) {}

    public function create(CreateUserAccountData $data): UserAccountData
    {
        Validator::make([
            'organization_id' => $data->organizationId,
            'name' => $data->name,
            'email' => $data->email,
            'username' => $data->username,
            'phone' => $data->phone,
            'password' => $data->password,
        ], [
            'organization_id' => ['required', 'ulid'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'username' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', Password::defaults()],
        ])->validate();

        $normalizedPhone = $data->phone !== null
            ? IssuePhonePasswordResetOtp::normalizePhone($data->phone)
            : null;

        if ($data->phone !== null && $normalizedPhone === null) {
            throw BusinessRuleViolation::make(
                'identity.phone_invalid',
                'identity::validation.phone_invalid',
            );
        }

        $user = $this->registerUser->execute([
            'organization_id' => $data->organizationId,
            'name' => $data->name,
            'email' => $data->email,
            'username' => $data->username,
            'phone' => $normalizedPhone,
            'password' => $data->password,
            'phone_country' => $data->phoneCountry,
            'locale' => $data->locale,
            'timezone' => $data->timezone,
        ]);

        return self::toDto($user);
    }

    public function confirmExistingAccount(
        string $organizationId,
        string $userId,
        ?string $email,
        ?string $phone,
    ): UserAccountData {
        /** @var User|null $user */
        $user = User::query()
            ->forOrganization($organizationId)
            ->whereKey($userId)
            ->first();

        $normalizedEmail = $email !== null ? mb_strtolower(trim($email)) : null;
        $normalizedPhone = $phone !== null ? IssuePhonePasswordResetOtp::normalizePhone($phone) : null;
        $emailMatches = $normalizedEmail !== null && $normalizedEmail !== ''
            && $user?->email !== null
            && hash_equals(mb_strtolower($user->email), $normalizedEmail);
        $phoneMatches = $normalizedPhone !== null
            && $user?->phone !== null
            && hash_equals($user->phone, $normalizedPhone);

        if ($user === null || (!$emailMatches && !$phoneMatches)) {
            throw BusinessRuleViolation::make(
                'identity.account_link_unverified',
                'identity::errors.account_link_unverified',
            );
        }

        return self::toDto($user);
    }

    private static function toDto(User $user): UserAccountData
    {
        return new UserAccountData(
            id: $user->id,
            organizationId: $user->organization_id,
            name: $user->name,
            email: $user->email,
            username: $user->username,
            phone: $user->phone,
            status: $user->status->value,
        );
    }
}
