<?php

declare(strict_types=1);

namespace Modules\Organization\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Organization\Database\Factories\OrganizationFactory;
use Modules\Organization\Domain\Contracts\OrganizationSettingQueries;
use Tests\TestCase;

final class OrganizationSettingQueriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_reads_json_setting_values_through_the_public_contract(): void
    {
        $organization = OrganizationFactory::new()->create();

        DB::table('organization_settings')->insert([
            'id' => (string) Str::ulid(),
            'organization_id' => $organization->id,
            'key' => 'username_prefix',
            'value' => json_encode('academy', JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        /** @var OrganizationSettingQueries $queries */
        $queries = app(OrganizationSettingQueries::class);

        $this->assertSame('academy', $queries->value($organization->id, 'username_prefix'));
        $this->assertNull($queries->value($organization->id, 'missing'));
    }
}
