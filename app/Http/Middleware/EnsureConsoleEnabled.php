<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureConsoleEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless((bool) config('console.enabled'), 404);

        $user = $request->user();
        if ($user !== null && method_exists($user, 'canLogIn')) {
            abort_unless($user->canLogIn(), 403);
        }

        $response = $next($request);
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');

        return $response;
    }
}
