<?php

declare(strict_types=1);

namespace Modules\Staff\Tests\Unit;

use Modules\Staff\Domain\Enums\StaffGender;
use PHPUnit\Framework\TestCase;

final class StaffGenderTest extends TestCase
{
    public function test_gender_values_match_the_student_teacher_matching_contract(): void
    {
        $this->assertSame(
            ['male', 'female'],
            array_map(
                static fn (StaffGender $gender): string => $gender->value,
                StaffGender::values(),
            ),
        );
    }
}
