<?php

declare(strict_types=1);

namespace Modules\Identity\Tests\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * يهيّئ مؤسسة اختبارية لتفادي قيد FK على users.organization_id.
 *
 * Identity لا يعرف جداول غيره في كود التشغيل — هذه المساعدة للاختبار فقط،
 * وتهيّئ أقل صف ممكن وفق هجرة organizations (id/name/slug والباقي افتراضي).
 */
trait CreatesTestOrganization
{
    protected string $organizationId;

    protected function createTestOrganization(): string
    {
        $this->organizationId = (string) Str::ulid();

        DB::table('organizations')->insert([
            'id' => $this->organizationId,
            'name' => json_encode(['ar' => 'مدرسة الاختبار', 'en' => 'Test School'], JSON_UNESCAPED_UNICODE),
            'slug' => Str::slug('test-school').'-'.strtolower($this->organizationId),
            'created_at' => now()->utc(),
            'updated_at' => now()->utc(),
        ]);

        return $this->organizationId;
    }
}
