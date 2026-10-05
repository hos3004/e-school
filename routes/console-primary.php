<?php

declare(strict_types=1);

use App\Http\Controllers\Console\PrimaryConsoleController;
use Illuminate\Support\Facades\Route;

// Registered only when Filament has moved to /v2, avoiding route collisions
// and preserving the current panel when primary mode is disabled.
if ((bool) config('console.enabled') && (bool) config('console.primary')) {
    Route::get('/admin/{path?}', PrimaryConsoleController::class)
        ->where('path', '.*')
        ->name('console.primary.legacy');
}
