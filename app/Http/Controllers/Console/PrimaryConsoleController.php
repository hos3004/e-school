<?php

declare(strict_types=1);

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class PrimaryConsoleController extends Controller
{
    public function __invoke(Request $request, ?string $path = null): RedirectResponse
    {
        abort_unless((bool) config('console.enabled') && (bool) config('console.primary'), 404);
        $path = trim($path ?? '', '/');
        if ($path === '' || $path === 'login') {
            if ($request->user() === null) {
                $request->session()->put('url.intended', route('console.home'));

                return redirect()->route('login');
            }

            return redirect()->route('console.home');
        }

        // The destination always starts with a local panel prefix. Only read
        // routes are aliased; legacy writes retain their original endpoints.
        $target = '/v2/'.$path;
        $query = $request->getQueryString();

        return redirect()->to($target.($query === null ? '' : '?'.$query));
    }
}
