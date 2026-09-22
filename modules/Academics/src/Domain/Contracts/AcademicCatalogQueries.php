<?php

declare(strict_types=1);

namespace Modules\Academics\Domain\Contracts;

use Modules\Academics\Domain\ValueObjects\AcademicCatalogItemData;

/** قراءة خفيفة للدليل الأكاديمي دون تسريب نماذج Eloquent. */
interface AcademicCatalogQueries
{
    /** @return list<AcademicCatalogItemData> */
    public function programs(string $organizationId): array;

    /** @return list<AcademicCatalogItemData> */
    public function courses(string $organizationId, string $programId): array;

    /** @return list<AcademicCatalogItemData> */
    public function levels(string $organizationId, string $programId): array;

    /**
     * @param list<string> $programIds
     * @return array<string, AcademicCatalogItemData>
     */
    public function programsByIds(string $organizationId, array $programIds): array;

    /**
     * @param list<string> $courseIds
     * @return array<string, AcademicCatalogItemData>
     */
    public function coursesByIds(string $organizationId, array $courseIds): array;

    /**
     * @param list<string> $levelIds
     * @return array<string, AcademicCatalogItemData>
     */
    public function levelsByIds(string $organizationId, array $levelIds): array;

    /**
     * حقائق البنية الأكاديمية للبرنامج، لحصيلة إقفاله.
     *
     * `courses_active` وحده مانع: كورس نشط تحت البرنامج يعني أن البرنامج لم ينتهِ
     * بعد. أما `courses_open` فيُذكر في الحصيلة ولا يمنع — كورس معطَّل ولم يُؤرشَف
     * ليس شغلًا قائمًا، لكن إخفاءه من البطاقة يجعلها تدّعي أن كل ما تحته أُقفل.
     *
     * @return array{levels_total: int, courses_total: int, courses_open: int, courses_active: int, courses_closed: int}
     */
    public function closureFactsForProgram(string $organizationId, string $programId): array;

    /**
     * معرّفات كورسات البرنامج — تُمرَّر إلى موديول الحصص لتجميع حصيلته.
     *
     * @return list<string>
     */
    public function courseIdsForProgram(string $organizationId, string $programId): array;

    /**
     * موضع الكورس وحجمه المخطَّط، لحصيلة إقفاله.
     *
     * @return array{level_id: string|null, program_id: string|null, planned_sessions: int|null, is_active: bool}
     */
    public function closureFactsForCourse(string $organizationId, string $courseId): array;

    /**
     * حقائق المستوى لحصيلة إقفاله.
     *
     * `courses_active` وحده مانع؛ `courses_open` يُذكر في الحصيلة ولا يمنع.
     *
     * @return array{program_id: string|null, courses_total: int, courses_open: int, courses_active: int, courses_closed: int}
     */
    public function closureFactsForLevel(string $organizationId, string $levelId): array;

    /**
     * معرّفات كورسات المستوى — تُمرَّر إلى موديول الحصص لتجميع حصيلته.
     *
     * @return list<string>
     */
    public function courseIdsForLevel(string $organizationId, string $levelId): array;
}
