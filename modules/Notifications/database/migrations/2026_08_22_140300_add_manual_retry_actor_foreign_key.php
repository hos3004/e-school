<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_outbox', function (Blueprint $table): void {
            // سجل المنفّذ يبقى مرجعًا قابلًا للتدقيق، لذلك يمنع حذف حسابه.
            $table->foreign('last_manual_retry_by')
                ->references('id')
                ->on('users')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('notification_outbox', function (Blueprint $table): void {
            $table->dropForeign(['last_manual_retry_by']);
        });
    }
};
