<?php

declare(strict_types=1);

namespace Modules\Academics\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Academics\Domain\Models\Course;
use Modules\Academics\Domain\Models\Level;
use Modules\Academics\Domain\Models\Program;

/**
 * بيانات تجريبية للبرامج والمستويات والكورسات.
 *
 * المؤسسة يملكها موديول Organization — هذا البذر يستهلك الموجودة
 * ولا ينشئ واحدة، حفاظًا على حدود الموديولات.
 */
final class AcademicsSeeder extends Seeder
{
    public function run(): void
    {
        $organizationId = $this->ensureOrganization();

        $program = Program::query()->firstOrCreate(
            ['code' => 'P001'],
            [
                'organization_id' => $organizationId,
                'name' => ['ar' => 'البرنامج العام', 'en' => 'General Program'],
                'description' => [
                    'ar' => 'برنامج تجريبي يغطي المستويات الأساسية.',
                    'en' => 'Demo program covering the core levels.',
                ],
                'duration_weeks' => 32,
                'default_session_minutes' => 60,
                'default_rate' => 7500,
                'currency' => 'EGP',
                'is_active' => true,
                'sort_order' => 1,
            ],
        );

        $levels = [
            ['code' => 'L001', 'name' => ['ar' => 'المستوى الأول', 'en' => 'Level One']],
            ['code' => 'L002', 'name' => ['ar' => 'المستوى الثاني', 'en' => 'Level Two']],
        ];

        foreach ($levels as $index => $levelData) {
            Level::query()->firstOrCreate(
                ['program_id' => (string) $program->getKey(), 'code' => $levelData['code']],
                [
                    'name' => $levelData['name'],
                    'sort_order' => $index + 1,
                ],
            );
        }

        Course::query()->firstOrCreate(
            ['code' => 'C001'],
            [
                'organization_id' => $organizationId,
                'level_id' => (string) Level::query()
                    ->where('program_id', $program->getKey())
                    ->where('code', 'L1')
                    ->value('id'),
                'name' => ['ar' => 'مقدمة في الرياضيات', 'en' => 'Introduction to Mathematics'],
                'description' => [
                    'ar' => 'كورس تجريبي لأساسيات الرياضيات.',
                    'en' => 'Demo course for mathematics basics.',
                ],
                'total_sessions' => 24,
                'is_active' => true,
            ],
        );

        Course::query()->firstOrCreate(
            ['code' => 'C002'],
            [
                'organization_id' => $organizationId,
                'level_id' => (string) Level::query()
                    ->where('program_id', $program->getKey())
                    ->where('code', 'L2')
                    ->value('id'),
                'name' => ['ar' => 'أساسيات الفيزياء', 'en' => 'Physics Basics'],
                'description' => [
                    'ar' => 'كورس تجريبي لمفاهيم الفيزياء الأولى.',
                    'en' => 'Demo course for first physics concepts.',
                ],
                'total_sessions' => 16,
                'is_active' => true,
            ],
        );
    }

    private function ensureOrganization(): string
    {
        $existing = DB::table('organizations')->orderBy('created_at')->value('id');

        if (is_string($existing) && $existing !== '') {
            return $existing;
        }

        $demoOrganizationId = '01JDEMOORGANIZATION0000000';

        DB::table('organizations')->insert([
            'id' => $demoOrganizationId,
            'name' => json_encode([
                'ar' => __('academics::messages.demo_school_name'),
                'en' => 'Demo School',
            ], JSON_UNESCAPED_UNICODE),
            'slug' => 'demo-school',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $demoOrganizationId;
    }
}
