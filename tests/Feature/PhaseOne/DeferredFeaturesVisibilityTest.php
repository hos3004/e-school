<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| حدّ المال في المرحلة الأولى — ADR-017
|--------------------------------------------------------------------------
| القرار ليس «المال كله مؤجَّل» ولا «المال كله مفتوح»، بل خط فاصل واحد:
|
|   المنصة **تحسب وتعرض** أجر المعلم  ←  مفعَّل
|   المنصة **تفوتر وتدفع**            ←  مؤجَّل
|
| هذه الاختبارات تحرس الخط نفسه، لا مجرد وجود مفتاح.
*/

it('enables teacher payroll calculation routes', function (): void {
    expect(config('features.payroll'))->toBeTrue()
        ->and(Route::has('payroll.periods.index'))->toBeTrue()
        ->and(Route::has('payroll.entries.index'))->toBeTrue()
        ->and(Route::has('payroll.adjustments.propose'))->toBeTrue()
        ->and(Route::has('portal.teacher.earnings'))->toBeTrue();
});

it('keeps student billing and teacher payouts deferred', function (): void {
    expect(config('features.student_billing'))->toBeFalse()
        ->and(config('features.teacher_payouts'))->toBeFalse()
        ->and(config('features.coupons'))->toBeFalse()
        ->and(config('features.subscriptions'))->toBeFalse();
});

it('does not load the billing module at all', function (): void {
    expect(config('modules.enabled.Billing'))->toBeFalse();
});

it('exposes no payout or payment route anywhere', function (): void {
    $money = collect(Route::getRoutes()->getRoutes())
        ->map(static fn ($route): string => (string) $route->uri())
        ->filter(static fn (string $uri): bool => str_contains($uri, 'payout')
            || str_contains($uri, 'invoice')
            || str_contains($uri, 'checkout')
            || str_contains($uri, 'payment'))
        ->values()
        ->all();

    expect($money)->toBe([]);
});
