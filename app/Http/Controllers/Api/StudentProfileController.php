<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Support\PortalData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Identity\Application\Actions\UpdatePassword;
use Modules\Identity\Application\Actions\UpdateUserProfile;
use Modules\Identity\Domain\Models\User;
use Modules\Identity\Presentation\Http\Requests\UpdatePasswordRequest;
use Modules\Identity\Presentation\Http\Requests\UpdateProfileRequest;

/**
 * ملف الطالب الشخصي للموبايل — مرآة Portal\StudentProfileController، نفس
 * Actions/FormRequests التي تستخدمها Portal\PortalProfileController بالضبط.
 */
final class StudentProfileController extends Controller
{
    public function __construct(
        private readonly PortalData $data,
        private readonly UpdateUserProfile $updateProfile,
        private readonly UpdatePassword $updatePassword,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $userId = (string) $request->user()?->getAuthIdentifier();
        $organizationId = (string) $request->user()?->getAttribute('organization_id');
        $studentProfileId = $this->data->studentProfileId($userId, $organizationId);

        return response()->json([
            'student' => $this->data->studentProfile($userId, $organizationId, app()->getLocale()),
            'account' => ['id' => $userId, ...(array) $this->data->accountSettings($userId, $organizationId)],
            'attendanceRate' => $studentProfileId === null
                ? null
                : $this->data->attendanceRate($studentProfileId, $organizationId),
        ]);
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->updateProfile->execute($user, $request->validated());

        $organizationId = (string) $user->getAttribute('organization_id');

        return response()->json([
            'account' => [
                'id' => (string) $user->getAuthIdentifier(),
                ...(array) $this->data->accountSettings((string) $user->getAuthIdentifier(), $organizationId),
            ],
        ]);
    }

    public function password(UpdatePasswordRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->updatePassword->execute(
            user: $user,
            currentPassword: (string) $request->validated('current_password'),
            newPassword: (string) $request->validated('password'),
        );

        return response()->json(['status' => 'updated']);
    }
}
