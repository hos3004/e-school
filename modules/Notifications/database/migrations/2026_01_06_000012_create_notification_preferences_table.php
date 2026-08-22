<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26)->index();
            $table->char('user_id', 26)->index();
            $table->string('category');
            $table->string('channel');
            $table->boolean('enabled');
            $table->timestampTz('updated_at')->nullable();

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations');
            $table->foreign('user_id')
                ->references('id')
                ->on('users');
        });

        DB::statement(
            'ALTER TABLE notification_preferences '
            .'ADD CONSTRAINT notification_preferences_user_category_channel_unique '
            .'UNIQUE (user_id, category, channel)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};
