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
}
