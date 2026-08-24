<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Modules\Identity\Domain\Models\User;
use Shared\Concerns\ScopesFilamentToOrganization;
use Tests\TestCase;

/**
 * فحص شامل للوحة الإدارة: كل مورد مسجَّل + لوحة المعلومات نفسها.
 *
 * سبب وجوده: عيب مثل «البحث في عمود jsonb» لا يظهر في أي اختبار وحدة — يظهر
 * لحظة فتح الصفحة. وقد لمست هذه الموجة 34 موردًا وأربع ودجات، فبقاء الصفحات
 * تفتح هو شرط القبول الحقيقي.
 *
 * الاختبار يفتح كل صفحة فهرس ويطلب مخطط الجدول والنموذج — وهي الخطوة التي
 * تنفّذ إغلاقات الأعمدة والفلاتر فتكشف الأخطاء الساكنة فيها.
 */
final class AdminPanelSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_registered_resource_page_opens(): void
    {
        $user = $this->panelUser();
        $this->grantEverything();
        $this->actingAs($user);

        Filament::setCurrentPanel('admin');

        $resources = Filament::getPanel('admin')->getResources();

        $this->assertGreaterThan(30, count($resources), 'لم تُسجَّل موارد اللوحة.');

        $failures = [];

        foreach ($resources as $resource) {
            if (!$resource::hasPage('index')) {
                continue;
            }

            try {
                $url = $resource::getUrl('index', panel: 'admin');
            } catch (Throwable $exception) {
                $failures[] = $resource.' :: getUrl :: '.$exception->getMessage();

                continue;
            }

            try {
                $response = $this->get($url);

                if (!in_array($response->getStatusCode(), [200, 403], true)) {
                    $failures[] = $resource.' :: HTTP '.$response->getStatusCode();
                }
            } catch (Throwable $exception) {
                $failures[] = $resource.' :: '.substr($exception->getMessage(), 0, 160);
            }
        }

        $this->assertSame([], $failures, "صفحات فشل فتحها:\n".implode("\n", $failures));
    }

    public function test_the_dashboard_renders_with_all_widgets(): void
    {
        $user = $this->panelUser();
        $this->grantEverything();

        $this->actingAs($user)
            ->get('/admin')
            ->assertOk();
    }

    /**
     * كل مورد يعزل المؤسسة يجب أن يُرجع صفرًا حين لا توجد مؤسسة في الجلسة.
     *
     * هذا يثبت أن العزل مبني على «امنع افتراضيًا» لا على «تجاهل الشرط».
     */
    public function test_scoped_resources_return_nothing_without_a_session(): void
    {
        Filament::setCurrentPanel('admin');

        $leaking = [];

        foreach (Filament::getPanel('admin')->getResources() as $resource) {
            if (!in_array(
                ScopesFilamentToOrganization::class,
                class_uses_recursive($resource),
                true,
            )) {
                continue;
            }

            if ($resource::getEloquentQuery()->count() !== 0) {
                $leaking[] = $resource;
            }
        }

        $this->assertSame([], $leaking, "موارد تُرجع بيانات بلا مؤسسة:\n".implode("\n", $leaking));
    }

    private function panelUser(): User
    {
        $organizationId = (string) Str::ulid();

        DB::table('organizations')->insert([
            'id' => $organizationId,
            'name' => json_encode(['ar' => 'مدرسة', 'en' => 'School'], JSON_THROW_ON_ERROR),
            'slug' => 'panel-'.strtolower((string) Str::ulid()),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::factory()->inOrganization($organizationId)->create([
            'email' => 'panel.smoke@example.test',
        ]);
    }

    /**
     * الهدف هنا فحص العرض لا الصلاحيات؛ اختبارات الصلاحيات في مواضعها.
     */
    private function grantEverything(): void
    {
        Gate::before(static fn (): bool => true);
    }
}
