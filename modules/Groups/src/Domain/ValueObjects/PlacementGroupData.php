<?php

declare(strict_types=1);

namespace Modules\Groups\Domain\ValueObjects;

final readonly class PlacementGroupData
{
    /**
     * @param array<string, string> $name
     * @param list<string> $teacherProfileIds
     * @param int|null $capacity `null` = مجموعة قيد التخطيط لم تُحدَّد سعتها بعد
     * @param int $occupiedSeats المقاعد المشغولة — النشط والمعلّق معًا
     * @param int $remainingSeats المقاعد المتبقية حتى السقف الفعلي
     * @param string $status قيمة `GroupStatus`
     */
    public function __construct(
        public string $id,
        public string $code,
        public array $name,
        public ?int $capacity,
        public int $occupiedSeats,
        public array $teacherProfileIds,
        public string $status = 'active',
        public int $remainingSeats = 0,
        public ?string $startsOn = null,
        public ?string $endsOn = null,
    ) {}

    /** هل المجموعة ما زالت مسودة تحتاج استكمالًا قبل التفعيل؟ */
    public function isDraft(): bool
    {
        return $this->status === 'planning';
    }
}
