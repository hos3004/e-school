<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Controllers\Learning\LearningDestination;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class RedirectPrimaryLearningPortal
{
    public function __construct(private LearningDestination $destination) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ((bool) config('console.enabled') && (bool) config('console.primary')
            && in_array($request->method(), ['GET', 'HEAD'], true)
            && in_array($request->path(), ['student', 'teacher'], true)) {
            $target = $this->destination->portals($request)[$request->path()] ?? null;
            if ($target !== null) {
                return redirect()->to($target);
            }
        }

        return $next($request);
    }
}
