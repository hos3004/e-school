<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UpdateLocaleRequest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;

final class UpdateLocaleController extends Controller
{
    public function __invoke(UpdateLocaleRequest $request): RedirectResponse
    {
        $locale = (string) $request->validated('locale');
        $user = $request->user();

        abort_unless($user instanceof Model, 401);

        $user->setAttribute('locale', $locale);
        $user->save();
        $request->session()->put('locale', $locale);

        return back();
    }
}
