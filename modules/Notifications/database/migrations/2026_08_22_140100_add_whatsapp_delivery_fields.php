<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * حقول تسليم المزوّد على مستوى القيد والمحاولة.
 *
 * تُخزَّن هوية الرسالة لدى المزوّد (wamid من Meta أو Message-ID من البريد)
 * وحالة التسليم وسبب الفشل كأعمدة صريحة حتى يبقى التتبّع والتحقيق لاحقًا
 * ممكنًا دون تفكيك jsonb الردود الخام في كل قراءة.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_outbox', function (Blueprint $table): void {
            $table->string('external_message_id')->nullable()->index();
            $table->string('provider_status', 32)->nullable();
            $table->text('failure_reason')->nullable();
        });

        Schema::table('notification_delivery_attempts', function (Blueprint $table): void {
            $table->string('external_message_id')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('notification_outbox', function (Blueprint $table): void {
            $table->dropIndex(['external_message_id']);
            $table->dropColumn(['external_message_id', 'provider_status', 'failure_reason']);
        });

        Schema::table('notification_delivery_attempts', function (Blueprint $table): void {
            $table->dropIndex(['external_message_id']);
            $table->dropColumn('external_message_id');
        });
    }
};
