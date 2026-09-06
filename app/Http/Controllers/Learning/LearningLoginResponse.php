<?php

declare(strict_types=1);

namespace App\Http\Controllers\Learning;

use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse;
use Laravel\Fortify\Fortify;

final readonly class LearningLoginResponse implements LoginResponse, TwoFactorLoginResponse
{
    public function __construct(private LearningDestination $destination) {}

    public function toResponse($request): mixed
    {
        if ($request->wantsJson()) {
            return $request->is('two-factor-challenge') ? response()->json('', 204) : response()->json(['two_factor' => false]);
        }
        $fallback = (bool) config('console.enabled') ? $this->destination->forRequest($request) : null;

        return redirect()->intended($fallback ?? Fortify::redirects('login'));
    }
}
