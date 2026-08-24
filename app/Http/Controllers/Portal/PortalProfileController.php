<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Modules\Identity\Application\Actions\UpdatePassword;
use Modules\Identity\Application\Actions\UpdateUserProfile;
use Modules\Identity\Domain\Models\User;
use Modules\Identity\Presentation\Http\Requests\UpdatePasswordRequest;
use Modules\Identity\Presentation\Http\Requests\UpdateProfileRequest;

/**
 * كتابة بيانات الحساب من بوابات الطالب والمعلم.
 *
 * البوابات تعمل داخل مجموعة `web` بجلسة ومفتاح CSRF، بينما مسارات موديول
 * Identity تعيش خلف `auth:sanctum` وتُرجع JSON. هذا المتحكم يستدعي نفس
 * Application Actions ونفس FormRequests، ويُرجع استجابة Inertia صالحة —
 * فلا تتكرر قاعدة عمل ولا يُخترع مسار موازٍ.
 */
final class PortalProfileController extends Controller
{
    public function __construct(
        private readonly UpdateUserProfile $updateProfile,
        private readonly UpdatePassword $updatePassword,
    ) {}

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->updateProfile->execute($user, $request->validated());

        return back()->with('success', __('portal.account.profile_updated'));
    }

    public function password(UpdatePasswordRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->updatePassword->execute(
            user: $user,
            currentPassword: (string) $request->validated('current_password'),
            newPassword: (string) $request->validated('password'),
        );

        return back()->with('success', __('portal.account.password_updated'));
    }
}
