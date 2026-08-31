<?php

declare(strict_types=1);

namespace App\Application\DTO;

/**
 * حصيلة الفحص المسبق للتسكين الجماعي.
 *
 * الفحص لا يكتب شيئًا؛ غرضه أن يرى المستخدم النتيجة قبل التأكيد. تُعاد كل
 * الفحوص داخل المعاملة عند التنفيذ الفعلي، لأن ما بين الشاشتين قد تتغير
 * السعة أو الحالة.
 */
final readonly class BulkPlacementPreflight
{
    /**
     * @param list<BulkPlacementCandidate> $candidates
     * @param int|null $remainingSeats المقاعد المتبقية في المجموعة المختارة، أو null لمجموعة جديدة
     */
    public function __construct(
        public array $candidates,
        public ?int $remainingSeats,
        public ?string $groupLabel,
        public bool $groupIsDraft,
        public ?string $capacityWarning,
    ) {}

    /** @return list<BulkPlacementCandidate> */
    public function eligible(): array
    {
        return array_values(array_filter(
            $this->candidates,
            static fn (BulkPlacementCandidate $candidate): bool => $candidate->eligible,
        ));
    }

    /** @return list<BulkPlacementCandidate> */
    public function blocked(): array
    {
        return array_values(array_filter(
            $this->candidates,
            static fn (BulkPlacementCandidate $candidate): bool => !$candidate->eligible,
        ));
    }

    /** @return list<BulkPlacementCandidate> */
    public function alreadyMembers(): array
    {
        return array_values(array_filter(
            $this->candidates,
            static fn (BulkPlacementCandidate $candidate): bool => $candidate->alreadyMember,
        ));
    }

    public function selectedCount(): int
    {
        return count($this->candidates);
    }

    public function eligibleCount(): int
    {
        return count($this->eligible());
    }

    /** هل يوجد ما يستحق التنفيذ أصلًا؟ */
    public function hasWork(): bool
    {
        return $this->eligibleCount() > 0;
    }

    /** @return list<string> معرّفات ملفات الطلاب الصالحين */
    public function eligibleStudentProfileIds(): array
    {
        return array_values(array_map(
            static fn (BulkPlacementCandidate $candidate): string => (string) $candidate->studentProfileId,
            $this->eligible(),
        ));
    }
}
