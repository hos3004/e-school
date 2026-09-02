<?php

declare(strict_types=1);

namespace Tests\Feature;

use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\Identity\Domain\Models\User;
use Tests\TestCase;
use Throwable;

/**
 * لا صفحة صامتة: كل مورد يمنع الإنشاء يقول من أين تأتي سجلاته.
 *
 * أحد وعشرون موردًا كان يُرجع `canCreate(): false` بلا حالة فارغة ولا زر ولا
 * كلمة — جدولٌ فارغ يقرأه المستخدم كعطل بينما هو تصميم مقصود. و
 * `emptyStateHeading` كان مستخدمًا في **ملف واحد** بالمستودع كله.
 *
 * يُقاس هنا **الصفحة المعروضة** لا مصدر الكود: الغاية أن يقرأ المستخدم الشرح،
 * لا أن يوجد استدعاء في مكان ما.
 */
final class PageActionAffordanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_resource_that_blocks_creation_shows_its_origin_on_screen(): void
    {
        $this->actAsPanelUser();

        $silent = [];
        $checked = 0;

        foreach (Filament::getPanel('admin')->getResources() as $resource) {
            if (!$resource::shouldRegisterNavigation() || $resource::canCreate()) {
                continue;
            }

            if (!$resource::hasPage('index')) {
                continue;
            }

            try {
                // الجدول يُقرأ من الصفحة المُقلَعة نفسها لا من نص HTML: أسماء
                // أصناف Filament تتغيّر بين الإصدارات، أما كائن الجدول فهو
                // المصدر الذي يُبنى منه ما يراه المستخدم.
                $this->get($resource::getUrl('index', panel: 'admin'));

                $table = Livewire::test($resource::getPages()['index']->getPage())
                    ->instance()
                    ->getTable();
            } catch (Throwable $e) {
                $silent[] = $resource.' :: '.substr($e->getMessage(), 0, 120);

                continue;
            }

            $checked++;

            if (blank($table->getEmptyStateHeading()) || blank($table->getEmptyStateDescription())) {
                $silent[] = $resource.' :: بلا عنوان أو وصف موجِّه';
            }
        }

        // حارس ضد حلقة تمر فارغة فتنجح بلا أن تفحص شيئًا. العدد يتغيّر بمفاتيح
        // الميزات (الاختبارات والشهادات وغيرها) فالحد أدنى لا مساواة.
        $this->assertGreaterThanOrEqual(12, $checked, 'لم تُفحص الموارد المتوقعة.');
        $this->assertSame(
            [],
            $silent,
            "موارد بلا شرح معروض لمصدر سجلاتها:\n".implode("\n", $silent),
        );
    }

    /**
     * العينة تثبت أن النص المعروض هو نص الموديول نفسه لا عنوان Filament العام
     * «لا سجلات» — وهو الفرق بين تفسير وبين لافتة فارغة.
     */
    public function test_the_shown_text_is_the_module_explanation(): void
    {
        $this->actAsPanelUser();

        $expected = [
            '/admin/attendance-filaments' => 'attendance::origin',
            '/admin/discipline-actions' => 'discipline::origin.action',
            '/admin/payroll-entries' => 'payroll::origin.entry',
            '/admin/audit-logs' => 'audit::origin',
        ];

        foreach ($expected as $path => $prefix) {
            $html = (string) $this->get($path)->getContent();

            $this->assertStringContainsString(
                (string) __($prefix.'.heading'),
                $html,
                "الصفحة {$path} لا تعرض عنوان {$prefix}.heading",
            );

            $this->assertStringContainsString(
                (string) __($prefix.'.description'),
                $html,
                "الصفحة {$path} لا تعرض وصف {$prefix}.description",
            );
        }
    }

    private function actAsPanelUser(): void
    {
        Gate::before(static fn (): bool => true);

        $organizationId = (string) Str::ulid();

        DB::table('organizations')->insert([
            'id' => $organizationId,
            'name' => json_encode(['ar' => 'مدرسة', 'en' => 'School'], JSON_THROW_ON_ERROR),
            'slug' => 'affordance-'.strtolower((string) Str::ulid()),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::factory()->inOrganization($organizationId)->create([
            'email' => 'affordance@example.test',
        ]);

        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }
}
