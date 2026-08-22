<?php

declare(strict_types=1);

use Modules\Discipline\Domain\Enums\ViolationType;

it('derives countability from the config map, not from code', function () {
    foreach (ViolationType::cases() as $type) {
        $expected = (bool) config('discipline.countable_events.'.$type->value);

        expect($type->isCountable())->toBe($expected);
    }
});

it('resolves labels through translations', function () {
    expect(ViolationType::NoShow->label())->toBe(__('discipline::violations.types.no_show'))
        ->and(ViolationType::NoShow->label())->not->toBe('discipline::violations.types.no_show');
});
