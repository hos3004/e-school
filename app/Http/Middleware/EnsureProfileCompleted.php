<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\AccountProfile;
use Closure;
use Illuminate\Http\Request;
use Modules\Identity\Domain\Models\User;
use Symfony\Component\HttpFoundation\Response;

final class EnsureProfileCompleted
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (!$user instanceof User || ($user->profile_completed_at !== null && !$user->must_change_password)
            || $request->routeIs('profile.complete.*', 'logout', 'locale.update')
            || !app(AccountProfile::class)->exists($user)) {
            return $next($request);
        }
        $url = route('profile.complete.show');
        if ($request->expectsJson() && !$request->header('X-Inertia')) {
            return response()->json(['message' => __('profile_completion.notice'), 'code' => 'profile_completion_required', 'redirect' => $url], 403);
        }

        return redirect()->to($url);
    }
}
