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
 * ملف المعلم الشخصي للموبايل — نفس بيانات Portal\TeacherProfileController
 * (PortalData::teacherProfile/accountSettings) ونفس Actions/FormRequests
 * التي تستخدمها Portal\PortalProfileController بالضبط، فقط JSON بدل
 * Inertia/redirect. البريد لا يُعدَّل هنا (نفس قيد UpdateUserProfile).
 */
final class TeacherProfileController extends Controller
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
        $staffProfileId = $this->data->staffProfileId($userId, $organizationId);

        return response()->json([
            'teacher' => $staffProfileId === null ? null : $this->data->teacherProfile($userId, $organizationId),
            'account' => $this->data->accountSettings($userId, $organizationId),
        ]);
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->updateProfile->execute($user, $request->validated());

        $organizationId = (string) $user->getAttribute('organization_id');

        return response()->json([
            'account' => $this->data->accountSettings((string) $user->getAuthIdentifier(), $organizationId),
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
