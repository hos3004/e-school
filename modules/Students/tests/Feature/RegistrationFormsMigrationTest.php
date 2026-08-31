<?php

declare(strict_types=1);

namespace Modules\Students\Tests\Feature;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class RegistrationFormsMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_forms_migration_rolls_back_and_reapplies_cleanly(): void
    {
        $this->assertRegistrationFormsSchemaExists();

        /** @var Migration $migration */
        $migration = require base_path(
            'modules/Students/database/migrations/2026_08_31_160000_create_registration_forms_table.php',
        );

        $migration->down();

        try {
            $this->assertFalse(Schema::hasTable('registration_forms'));
            $this->assertFalse(Schema::hasColumn('registration_questions', 'registration_form_id'));
            $this->assertFalse(Schema::hasColumn('registration_applications', 'registration_form_id'));
        } finally {
            $migration->up();
        }

        $this->assertRegistrationFormsSchemaExists();
    }

    private function assertRegistrationFormsSchemaExists(): void
    {
        $this->assertTrue(Schema::hasTable('registration_forms'));
        $this->assertTrue(Schema::hasColumn('registration_questions', 'registration_form_id'));
        $this->assertTrue(Schema::hasColumn('registration_applications', 'registration_form_id'));
    }
}
