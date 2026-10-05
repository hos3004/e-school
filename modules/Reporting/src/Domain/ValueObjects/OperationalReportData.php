<?php

declare(strict_types=1);

namespace Modules\Reporting\Domain\ValueObjects;

final readonly class OperationalReportData
{
    /**
     * @param list<OperationalReportRow> $rows
     * @param array<string, int|float|string> $summary
     */
    public function __construct(
        public OperationalReportCriteria $criteria,
        public array $rows,
        public array $summary,
        public bool $limitExceeded = false,
    ) {}

    /** @return list<array<string, mixed>> */
    public function rowsAsArray(): array
    {
        return array_map(
            static fn (OperationalReportRow $row): array => $row->toArray(),
            $this->rows,
        );
    }
}
