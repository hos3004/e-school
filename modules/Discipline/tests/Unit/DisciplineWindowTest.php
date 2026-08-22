<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Modules\Discipline\Domain\ValueObjects\DisciplineWindow;

it('builds a monthly window key by default', function () {
    config()->set('discipline.counter_window', 'monthly');

    $window = DisciplineWindow::forDate(CarbonImmutable::parse('2026-08-22 14:30:00', 'UTC'));

    expect($window->key)->toBe('2026-08')
        ->and(strlen($window->key))->toBe(7);
});

it('builds a quarterly window key when configured', function () {
    config()->set('discipline.counter_window', 'quarterly');

    expect(DisciplineWindow::forDate(CarbonImmutable::parse('2026-01-15', 'UTC'))->key)->toBe('2026-Q1')
        ->and(DisciplineWindow::forDate(CarbonImmutable::parse('2026-04-01', 'UTC'))->key)->toBe('2026-Q2')
        ->and(DisciplineWindow::forDate(CarbonImmutable::parse('2026-12-31', 'UTC'))->key)->toBe('2026-Q4');
});

it('treats dates inside the same window as equal counters windows', function () {
    config()->set('discipline.counter_window', 'monthly');

    $a = DisciplineWindow::forDate(CarbonImmutable::parse('2026-08-01', 'UTC'));
    $b = DisciplineWindow::forDate(CarbonImmutable::parse('2026-08-31', 'UTC'));

    expect($a->equals($b))->toBeTrue();
});
