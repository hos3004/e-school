<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;

/*
 * قالب «تم تعديل مواعيد الحصص».
 *
 * تعديل جدول متكرر يعيد توليد عشرات الحصص. قبل هذا القالب لم يكن هناك ملخّص
 * واحد يقول المواعيد الجديدة، فكان الإشعار الوحيد المتاح هو إشعار كل حصة على
 * حدة — عشرون رسالة عن موعد واحد. الحصص المولّدة صارت صامتة، فلولا هذا
 * القالب لصار التعديل صامتًا تمامًا.
 *
 * idempotent: تتخطى أي صف موجود، فلا تدهس تخصيصًا سابقًا ولا تكرر الإدراج.
 */
return new class extends Migration
{
    private const EVENT_KEY = 'schedule.times_changed';

    /** @var list<string> */
    private const CHANNELS = ['in_app', 'email', 'whatsapp'];

    /** @var list<string> */
    private const LOCALES = ['ar', 'en'];

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

            foreach (self::CHANNELS as $channel) {
                $exists = DB::table('notification_templates')
                    ->whereNull('organization_id')
                    ->where('event_key', self::EVENT_KEY)
                    ->where('channel', $channel)
                    ->where('locale', $locale)
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::table('notification_templates')->insert([
                    'id' => (string) Str::ulid(),
                    'organization_id' => null,
                    'event_key' => self::EVENT_KEY,
                    'channel' => $channel,
                    'locale' => $locale,
                    'subject' => $template['subject'],
                    'body' => $template['body'],
                    'provider_template_name' => $channel === 'whatsapp'
                        ? str_replace('.', '_', self::EVENT_KEY)
                        : null,
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
    }

    public function down(): void
    {
        // القوالب العامة فقط؛ أي نسخة خاصة بمؤسسة تبقى كما هي.
        DB::table('notification_templates')
            ->whereNull('organization_id')
            ->where('event_key', self::EVENT_KEY)
            ->delete();
    }
};
