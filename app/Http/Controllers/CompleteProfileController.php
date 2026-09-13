<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\CompleteProfileRequest;
use App\Services\AccountProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Identity\Domain\Models\User;
use Modules\Organization\Domain\Contracts\GeographyQueries;

final class CompleteProfileController extends Controller
{
    public function show(Request $request, AccountProfile $profiles, GeographyQueries $geo): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('update', $user), 403);
        $profile = $profiles->student($user) ?? $profiles->teacher($user);
        abort_if($profile === null, 403);
        $countryId = (string) $profile->country_id;

        return Inertia::render('Learning/CompleteProfile', [
            'profile' => [...$user->only(['name', 'email', 'phone', 'timezone']), ...$profile->only(['country_id', 'region_id', 'region_name', 'city', 'gender']), 'date_of_birth' => $profile->date_of_birth?->toDateString()],
            'required' => $user->profile_completed_at === null,
            'mustChangePassword' => $user->must_change_password,
            'countries' => collect($geo->countries())->reject(fn ($c): bool => $c->iso2 === 'ZZ')->map(fn ($c): array => ['id' => $c->id, 'name' => $c->name[app()->getLocale()] ?? $c->name['ar'] ?? $c->iso2])->values()->all(),
            'regions' => $this->regionOptions($geo, $countryId),
            'timezones' => timezone_identifiers_list(),
        ]);
    }

    public function regions(Request $request, GeographyQueries $geo): JsonResponse
    {
        return response()->json($this->regionOptions($geo, (string) $request->query('country_id')));
    }

    /** @return list<array{id: string, name: string}> */
    private function regionOptions(GeographyQueries $geo, string $countryId): array
    {
        if (!Str::isUlid($countryId)) {
            return [];
        }

        return collect($geo->regionsOf($countryId))->reject(fn ($r): bool => str_ends_with($r->code, '0000') || $r->code === 'UNSPECIFIED')
            ->map(fn ($r): array => ['id' => $r->id, 'name' => $r->name[app()->getLocale()] ?? $r->name['ar'] ?? $r->code])->values()->all();
    }

    public function store(CompleteProfileRequest $request, AccountProfile $profiles, AuditRecorder $audit, GeographyQueries $geo): RedirectResponse
    {
        $data = $request->validated();
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $newPassword = isset($data['password']) && is_string($data['password']) && $data['password'] !== ''
            ? $data['password'] : null;
        DB::transaction(function () use ($actor, $profiles, $data, $audit, $geo, $newPassword): void {
            $user = User::query()->lockForUpdate()->findOrFail($actor->id);
            $student = $profiles->student($user);
            $teacher = $profiles->teacher($user);
            abort_if($student === null && $teacher === null, 403);
            $before = ['account' => $user->only(['name', 'email', 'phone', 'phone_country', 'timezone', 'profile_completed_at', 'must_change_password']), 'student' => $student?->only(['country_id', 'region_id', 'region_name', 'city', 'date_of_birth', 'gender']), 'teacher' => $teacher?->only(['country_id', 'region_id', 'region_name', 'city', 'date_of_birth', 'gender', 'phone'])];
            $user->fill(Arr::only($data, ['name', 'email', 'phone', 'timezone']));
            $user->phone_country = collect($geo->countries())->firstWhere('id', $data['country_id'])?->iso2;
            if ($user->isDirty('email')) {
                $user->email_verified_at = null;
            }
            if ($user->isDirty('phone')) {
                $user->phone_verified_at = null;
            }
            $user->profile_completed_at = now();
            if ($newPassword !== null) {
                $user->password = Hash::make($newPassword);
            }
            $user->must_change_password = false;
            $user->save();
            $profileData = Arr::only($data, ['country_id', 'region_id', 'region_name', 'city', 'date_of_birth', 'gender']);
            $student?->update($profileData);
            $teacher?->update([...$profileData, 'phone' => $data['phone']]);
            $audit->record($user->organization_id, $user->id, 'user', 'identity.profile_completed', User::class, $user->id, $before, [
                'account' => $user->only(['name', 'email', 'phone', 'phone_country', 'timezone', 'profile_completed_at']), 'profile' => $profileData,
                'password_changed' => $newPassword !== null,
            ], __('profile_completion.audit'));
        });

        // نسخة المستخدم في الجلسة ما زالت تحمل كلمة المرور القديمة، وAuthenticateSession
        // يكتب بصمتها في نهاية الطلب فيطرد صاحب الحساب في الطلب التالي. التحديث يبقيه داخل الموقع.
        if ($newPassword !== null) {
            $actor->refresh();
        }

        return redirect()->to(($profiles->student($actor) !== null ? (config('console.enabled') ? '/learn/student' : '/student') : (config('console.enabled') ? '/learn/teacher' : '/teacher')))->with('success', __('profile_completion.saved'));
    }
}
