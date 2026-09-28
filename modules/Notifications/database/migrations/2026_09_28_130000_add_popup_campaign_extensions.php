<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * إضافة حقول الميديا والاستثناء ونمط العرض والإغلاق التلقائي وروابط النص
 * إلى popup_campaigns. إضافية بحتة — لا تعديل ولا حذف لأي عمود قائم.
 *
 * - display_mode: نمط عرض الشريط (bottom_banner) أو ملء الشاشة (fullscreen).
 * - excluded_audiences: نفس شكل audiences؛ الاستثناء يفوز دائمًا على المطابقة الموجبة.
 * - auto_dismiss_seconds: إغلاق تلقائي بعد N ثانية؛ يُعامل كمخرج آمن في hasSafeExit().
 * - links: قائمة {text, url} حيث url إما https:// خارجي أو popup-media:{id} داخلي
 *   يشير لملف قابل للتنزيل مرفوع ضمن نفس الحملة.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('popup_campaigns', function (Blueprint $table): void {
            $table->string('display_mode', 16)->default('bottom_banner')->after('placement');
            $table->jsonb('excluded_audiences')->nullable()->after('audiences');
            $table->unsignedSmallInteger('auto_dismiss_seconds')->nullable()->after('frequency');
            $table->jsonb('links')->nullable()->after('action_target');
        });
    }

    public function down(): void
    {
        Schema::table('popup_campaigns', function (Blueprint $table): void {
            $table->dropColumn(['display_mode', 'excluded_audiences', 'auto_dismiss_seconds', 'links']);
        });
    }
};
