<?php

declare(strict_types=1);

namespace Modules\Audit\Tests\Support;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;

/**
 * يهيّئ قاعدة بيانات الاختبار بهجرات كل الموديولات (السلوك الافتراضي لـ RefreshDatabase).
 *
 * لا يحصر الهجرة في مسار هذا الموديول: علم RefreshDatabaseState::$migrated
 * عام على مستوى العملية، فحصر المسار هنا يجعل أي كلاس اختبار لاحق في نفس
 * العملية يتخطى migrate:fresh ويجد قاعدة ناقصة الجداول.
 *
 * كما يضمن تحميل الـ Factory يدويًا (خارج خرائط autoload الحالية)،
 * ويصحّح فئة التاريخ بعد تهيئة التطبيق حتى تعمل المساعدات الزمنية.
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
}
