<?php

declare(strict_types=1);

use Modules\AcademicReports\Presentation\Filament\Resources\MonthlyReportResource;
use Modules\AcademicReports\Tests\Support\ApiUser;

it('keys the monthly report resource query to the current organization on every request', function (): void {
    $firstOrganizationId = (string) str()->ulid();
    $secondOrganizationId = (string) str()->ulid();

    $this->actingAs(new ApiUser('first', $firstOrganizationId));
    $firstQuery = MonthlyReportResource::getEloquentQuery();

    expect($firstQuery->getBindings())->toContain($firstOrganizationId);

    $this->actingAs(new ApiUser('second', $secondOrganizationId));
    $secondQuery = MonthlyReportResource::getEloquentQuery();

    expect($secondQuery->getBindings())->toContain($secondOrganizationId)
        ->and($secondQuery->getBindings())->not->toContain($firstOrganizationId);
});
