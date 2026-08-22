<?php

declare(strict_types=1);

namespace Modules\Audit\Tests\Support;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;

/**
 * يهيّئ قاعدة بيانات الاختبار بهجرات موديول Audit فقط.
 *
 * الموديولات الأخرى تُبنى بالتوازي وقد لا تكون هجراتها قابلة للتشغيل
 * بعد؛ اختبارات هذا الموديول لا تعتمد على أي جدول خارجي.
 *
 * كما تضمن تحميل الـ Factory يدويًا (خارج خرائط autoload الحالية)،
 * وتصحّح فئة التاريخ بعد تهيئة التطبيق حتى تعمل المساعدات الزمنية.
 */
trait RefreshAuditDatabase
{
    use RefreshDatabase;

    public static function setUpBeforeClass(): void
    {
        require_once dirname(__DIR__, 2).'/database/factories/AuditLogFactory.php';
    }

    protected function setUp(): void
    {
        parent::setUp();

        Date::use(CarbonImmutable::class);

        if (empty(config('app.key'))) {
            config(['app.key' => 'base64:eHh4eHh4eHh4eHh4eHh4eHh4eHh4eHh4eHh4eHh4eHg=']);
        }
    }

    /**
     * @return array<string, bool|int|string>
     */
    protected function migrateFreshUsing(): array
    {
        return [
            '--drop-views' => true,
            '--path' => 'modules/Audit/database/migrations',
            '--realpath' => true,
            '--force' => true,
        ];
    }
}
