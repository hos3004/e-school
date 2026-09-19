<?php

declare(strict_types=1);

namespace Modules\Payroll\Domain\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

interface TeacherDuesOperations
{
    /**
     * `approved` يميّز التسوية التي اعتُمدت في نفس الطلب — حين لا تشترط
     * السياسة معتمِدًا مختلفًا — عن تلك التي ما زالت تنتظر قرارًا، فتختلف
     * رسالة النتيجة وموضع المبلغ من الصافي.
     *
     * @param array<string, mixed> $data
     * @return array{periodId: string, staffProfileId: string, adjustmentId: string, approved: bool}
     */
    public function propose(Authenticatable $actor, string $periodId, array $data): array;

    /** @return array{periodId: string, staffProfileId: string, adjustmentId: string, approved: bool} */
    public function decide(Authenticatable $actor, string $adjustmentId, bool $approve, string $reason): array;
}
