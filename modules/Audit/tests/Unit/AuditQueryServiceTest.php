<?php

declare(strict_types=1);

namespace Modules\Audit\Tests\Unit;

use Modules\Audit\Domain\Contracts\AuditQueryService;
use Modules\Audit\Domain\Models\AuditLog;
use Modules\Audit\Infrastructure\Persistence\AuditLogQueryService;
use Modules\Audit\Tests\Support\RefreshAuditDatabase;
use Tests\TestCase;

/**
 * خدمة الاستعلام — DTOs فقط عبر العقد العام.
 */
final class AuditQueryServiceTest extends TestCase
{
    use RefreshAuditDatabase;

    public function test_returns_paginated_dtos_for_one_organization_with_filters(): void
    {
        $org = '01ORGTEST00000000000000000';

        AuditLog::factory()->create([
            'organization_id' => $org,
            'action' => 'logged_in',
            'created_at' => now()->utc()->subHour(),
        ]);
        AuditLog::factory()->create([
            'organization_id' => $org,
            'action' => 'updated',
        ]);
        AuditLog::factory()->create([
            'organization_id' => '01ORGOTHER0000000000000000',
            'action' => 'logged_in',
        ]);

        /** @var AuditQueryService $service */
        $service = app(AuditQueryService::class);

        $all = $service->paginateForOrganization($org);
        self::assertSame(2, $all->total());
        self::assertNotInstanceOf(AuditLog::class, $all->items()[0]);
        self::assertIsString($all->items()[0]->id);

        $filtered = $service->paginateForOrganization($org, ['action' => 'logged_in']);
        self::assertSame(1, $filtered->total());
        self::assertSame('logged_in', $filtered->items()[0]->action);

        self::assertSame('updated', $all->items()[0]->action);
    }

    public function test_contract_is_bound_to_the_infrastructure_implementation(): void
    {
        self::assertInstanceOf(
            AuditLogQueryService::class,
            app(AuditQueryService::class),
        );
    }
}
