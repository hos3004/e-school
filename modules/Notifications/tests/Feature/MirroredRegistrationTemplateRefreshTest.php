<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;
use Shared\Testing\Fixtures;

uses(RefreshDatabase::class);

/*
 * شاشة قوالب واتساب في اللوحة تصنع نسخة مؤسسة لكل حدث، والمحرّك يفضّلها على
 * النص العام. نسخة لم يعدّلها أحد يجب أن تتبع النص العام الجديد، ونص كتبته
 * المؤسسة فعلًا يجب أن يبقى كما هو.
 */
it('refreshes an untouched organization mirror and keeps a real customisation', function (): void {
    $organizationId = Fixtures::organizationId();
    $previousBody = 'تم اعتماد طلب التسجيل بنجاح. يمكنك الآن متابعة خطوات البدء.';
    $customBody = 'نص كتبته المؤسسة بنفسها ولا يشبه النص العام.';

    $insert = function (string $channel, string $body) use ($organizationId): string {
        $id = (string) Str::ulid();

        DB::table('notification_templates')->insert([
            'id' => $id,
            'organization_id' => $organizationId,
            'event_key' => 'registration.approved',
            'channel' => $channel,
            'locale' => 'ar',
            'subject' => 'تم اعتماد التسجيل',
            'body' => $body,
            'provider_template_name' => 'registration_approved',
            'parameters' => json_encode([], JSON_UNESCAPED_UNICODE),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    };

    $mirrorId = $insert('whatsapp', $previousBody);
    $customId = $insert('in_app', $customBody);

    $migration = require base_path(
        'modules/Notifications/database/migrations/2026_09_20_200000_refresh_mirrored_registration_templates.php',
    );
    $migration->up();

    /** @var array<string, array{subject: string, body: string, parameters?: list<string>}> $templates */
    $templates = Lang::get('notifications::templates', [], 'ar');
    $expected = $templates['registration.approved'];

    $mirror = DB::table('notification_templates')->where('id', $mirrorId)->first();
    $custom = DB::table('notification_templates')->where('id', $customId)->first();

    expect($mirror?->body)->toBe($expected['body'])
        ->and($mirror?->body)->toContain('{{student_name}}')
        ->and(json_decode((string) $mirror?->parameters, true))
        ->toBe(array_values($expected['parameters'] ?? []))
        ->and($custom?->body)->toBe($customBody)
        ->and(json_decode((string) $custom?->parameters, true))->toBe([]);
});
