<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * إعدادات توجيه فئات الإشعارات لكل مؤسسة — تتحكم اللوحة في: أي قنوات لكل فئة،
 * وهل هي حرجة، وهل تخضع لساعات الهدوء. غياب الصف يعني اعتماد الافتراضي في
 * config/notifications.php، فلا يتغيّر سلوك أي مؤسسة لم تُخصّص إعداداتها.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_category_settings', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26);
            $table->string('category', 64);
            $table->jsonb('channels');
            $table->boolean('is_critical')->default(false);
            $table->boolean('respects_quiet_hours')->default(true);
            $table->timestampsTz();

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->cascadeOnDelete();

            $table->unique(['organization_id', 'category'], 'notification_category_settings_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_category_settings');
    }
};
