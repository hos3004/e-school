<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * زر «أنا مستعد» عند الطالب — إشعار تنبيهي للمعلم فقط، بلا أي أثر على
 * الحضور أو الحالة أو المستحقات. آخر ضغطة فقط تُحفظ (تحديث لا تراكم)
 * حتى يعرض ملف الحصة وقتًا واحدًا واضحًا.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('session_participants', function (Blueprint $table): void {
            $table->timestampTz('ready_pinged_at')->nullable()->after('current_joined_at');
        });
    }

    public function down(): void
    {
        Schema::table('session_participants', function (Blueprint $table): void {
            $table->dropColumn('ready_pinged_at');
        });
    }
};
