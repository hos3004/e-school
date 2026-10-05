<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

it('requires Sanctum authentication for private module APIs', function (string $method, string $uri): void {
    $this->json($method, $uri)->assertUnauthorized();
})->with([
    'assessments' => ['GET', '/api/assessments'],
    'certificates' => ['GET', '/api/certificates'],
    'integrations' => ['POST', '/api/integrations/connections'],
    'payroll' => ['GET', '/api/payroll/periods'],
    'recordings' => ['GET', '/api/recordings'],
    'sessions' => ['GET', '/api/sessions'],
]);

it('keeps the signed classroom webhook outside application authentication', function (): void {
    $route = Route::getRoutes()->getByName('classroom.webhook');

    expect($route)->not->toBeNull();

    $middleware = $route?->middleware() ?? [];

    expect($middleware)
        ->toContain('throttle:classroom-webhook')
        ->not->toContain('auth:sanctum');
});
