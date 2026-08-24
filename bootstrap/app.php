<?php

declare(strict_types=1);

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Shared\Module\ModuleRegistry;
use Shared\Support\BusinessRuleViolation;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        then: function (): void {
            ModuleRegistry::loadRoutes();
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            SetLocale::class,
            HandleInertiaRequests::class,
        ]);

        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontReport(BusinessRuleViolation::class);

        $exceptions->render(function (BusinessRuleViolation $violation, Request $request): JsonResponse|RedirectResponse {
            $correlationId = (string) ($request->header('X-Correlation-Id') ?: Str::ulid());

            /*
             * بوابات Inertia تعمل داخل مجموعة `web` ولا تفهم جسم JSON، فكانت
             * مخالفة قاعدة العمل تظهر للمستخدم كنص خام بلا سياق. الطلب الذي لا
             * ينتظر JSON يعود من حيث أتى برسالة الخطأ نفسها، بينما يحتفظ عملاء
             * الـAPI بعقد 422 كما هو.
             */
            if (!$request->expectsJson()) {
                return back()
                    ->withInput()
                    ->withErrors(['business_rule' => $violation->getMessage()])
                    ->with('error', $violation->getMessage());
            }

            return response()
                ->json([
                    'error' => [
                        'code' => $violation->rule,
                        'message' => $violation->getMessage(),
                        'details' => $violation->context,
                    ],
                    'meta' => [
                        'correlation_id' => $correlationId,
                    ],
                ], Response::HTTP_UNPROCESSABLE_ENTITY)
                ->header('X-Correlation-Id', $correlationId);
        });
    })
    ->create();
