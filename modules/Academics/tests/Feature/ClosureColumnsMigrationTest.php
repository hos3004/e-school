<?php

declare(strict_types=1);

namespace Modules\Academics\Tests\Feature;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Modules\Academics\Domain\Models\Course;
use Modules\Academics\Domain\Models\Level;
use Modules\Academics\Domain\Models\Program;
use Modules\Groups\Domain\Models\Group;
use Tests\TestCase;

interface ClosureReversibleMigration
{
    public function up(): void;

    public function down(): void;
}

/**
 * هجرات أعمدة الإقفال تنزل وتُعاد دون فقد صفوف.
 *
 * الرجوع يُسقط أعمدة الإقفال — وهذا متوقَّع — لكن الصفوف نفسها يجب أن تبقى:
 * برنامج أو كورس أو مجموعة لا يجوز أن يختفي لأن ميزةً أُلغيت.
 */
final class ClosureColumnsMigrationTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private const MIGRATIONS = [
        'modules/Academics/database/migrations/2026_09_21_220000_add_closure_to_programs_and_courses.php',
        'modules/Groups/database/migrations/2026_09_21_220100_add_closure_to_groups.php',
        'modules/Academics/database/migrations/2026_09_22_090000_add_closure_to_levels.php',
    ];

    public function test_closure_migrations_roll_back_and_reapply_without_losing_rows(): void
    {
        $this->assertClosureColumnsExist();

        $program = Program::factory()->create();
        $level = Level::factory()->for($program, 'program')->create();
        $course = Course::factory()->create([
            'organization_id' => $program->organization_id,
            'level_id' => $level->getKey(),
        ]);
        $group = Group::factory()->create(['organization_id' => $program->organization_id]);

        /** @var list<Migration&ClosureReversibleMigration> $migrations */
        $migrations = array_map(
            static fn (string $path): object => require base_path($path),
            self::MIGRATIONS,
        );

        foreach (array_reverse($migrations) as $migration) {
            $migration->down();
        }

        try {
            foreach (['programs', 'courses', 'groups', 'levels'] as $table) {
                self::assertFalse(
                    Schema::hasColumn($table, 'closed_at'),
                    "العمود closed_at كان يجب أن يُسقط من {$table}.",
                );
            }
        } finally {
            foreach ($migrations as $migration) {
                $migration->up();
            }
        }

        $this->assertClosureColumnsExist();

        self::assertTrue(Program::query()->whereKey($program->getKey())->exists());
        self::assertTrue(Level::query()->whereKey($level->getKey())->exists());
        self::assertTrue(Course::query()->whereKey($course->getKey())->exists());
        self::assertTrue(Group::query()->whereKey($group->getKey())->exists());
    }

    private function assertClosureColumnsExist(): void
    {
        foreach (['programs', 'courses', 'groups', 'levels'] as $table) {
            foreach (['closed_at', 'closed_by', 'closure_reason', 'closure_summary'] as $column) {
                self::assertTrue(
                    Schema::hasColumn($table, $column),
                    "العمود {$column} مفقود من {$table}.",
                );
            }
        }
    }
}
