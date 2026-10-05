<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Notifications\Domain\Models\NotificationTemplate;
use Tests\TestCase;

/**
 * القوالب الجديدة تصل بالهجرة لا بالـseeder.
 *
 * ملف اللغة مصدر للـseeder، والـseeders لا تُشغَّل على قاعدة الموقع. فلولا
 * هجرة البيانات لسقط كل تذكير برابط دخول وكل إخطار غياب بـtemplate_missing
 * بعد النشر مباشرة. هذا الاختبار يقرأ القاعدة بعد الهجرات وحدها.
 */
final class NotificationTemplateInstallationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_new_event_templates_exist_after_migrations_alone(): void
    {
        $eventKeys = [
            'session.join_link.teacher',
            'session.join_link.student',
            'discipline.absence_recorded',
        ];

        foreach ($eventKeys as $eventKey) {
            foreach (['in_app', 'email', 'whatsapp'] as $channel) {
                foreach (['ar', 'en'] as $locale) {
                    $template = NotificationTemplate::query()
                        ->whereNull('organization_id')
                        ->where('event_key', $eventKey)
                        ->where('channel', $channel)
                        ->where('locale', $locale)
                        ->first();

                    $this->assertInstanceOf(
                        NotificationTemplate::class,
                        $template,
                        'قالب مفقود: '.$eventKey.' · '.$channel.' · '.$locale,
                    );
                    $this->assertTrue($template->is_active);
                    $this->assertNotSame('', trim($template->body));
                }
            }
        }
    }

    public function test_the_join_link_templates_carry_the_link_variable(): void
    {
        foreach (['session.join_link.teacher', 'session.join_link.student'] as $eventKey) {
            $template = NotificationTemplate::query()
                ->whereNull('organization_id')
                ->where('event_key', $eventKey)
                ->where('channel', 'whatsapp')
                ->where('locale', 'ar')
                ->sole();

            // بلا join_url تصل رسالة تذكير بلا الرابط الذي بُنيت لأجله.
            $this->assertContains('join_url', $template->parameters);
            $this->assertStringContainsString('{{join_url}}', $template->body);
        }
    }
}
