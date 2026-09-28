<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;

/*
 * قالب حدث 'message.sent' الجديد (محادثات الرسائل المباشرة والجماعية).
 *
 * TemplateRenderer يقرأ من جدول notification_templates وحده؛ بلا هذه الهجرة
 * يسقط كل إشعار رسالة جديدة بـtemplate_missing فور نشر الميزة. القناة
 * in_app فقط لأن 'message_received' لا تحمل قناة أخرى في config/notifications.php،
 * وpush يصل تلقائيًا كصدى لها عبر PushMirrorDispatcher دون سطر outbox مستقل.
 *
 * idempotent: تتخطى أي صف موجود.
 */
return new class extends Migration
{
    private const string EVENT_KEY = 'message.sent';

    private const string CHANNEL = 'in_app';

    /** @var list<string> */
    private const array LOCALES = ['ar', 'en'];

    public function up(): void
    {
        $now = now();

        foreach (self::LOCALES as $locale) {
            /** @var array<string, array{subject: string, body: string, parameters?: list<string>}> $templates */
            $templates = Lang::get('notifications::templates', [], $locale);
            $template = $templates[self::EVENT_KEY] ?? null;

            if (!is_array($template)) {
                continue;
            }

            $exists = DB::table('notification_templates')
                ->whereNull('organization_id')
                ->where('event_key', self::EVENT_KEY)
                ->where('channel', self::CHANNEL)
                ->where('locale', $locale)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('notification_templates')->insert([
                'id' => (string) Str::ulid(),
                'organization_id' => null,
                'event_key' => self::EVENT_KEY,
                'channel' => self::CHANNEL,
                'locale' => $locale,
                'subject' => $template['subject'],
                'body' => $template['body'],
                'provider_template_name' => null,
                'parameters' => json_encode(
                    array_values($template['parameters'] ?? []),
                    JSON_UNESCAPED_UNICODE,
                ),
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('notification_templates')
            ->whereNull('organization_id')
            ->where('event_key', self::EVENT_KEY)
            ->where('channel', self::CHANNEL)
            ->delete();
    }
};
