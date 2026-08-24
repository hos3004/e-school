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
        Schema::table('classroom_events', function (Blueprint $table): void {
            $table->string('idempotency_key', 64)->nullable();
        });

        DB::table('classroom_events')
            ->select('id')
            ->orderBy('id')
            ->eachById(function (object $event): void {
                DB::table('classroom_events')
                    ->where('id', $event->id)
                    ->update(['idempotency_key' => hash('sha256', (string) $event->id)]);
            }, column: 'id');

        Schema::table('classroom_events', function (Blueprint $table): void {
            $table->unique('idempotency_key', 'classroom_events_idempotency_key_unique');
        });
    }

    public function down(): void
    {
        Schema::table('classroom_events', function (Blueprint $table): void {
            $table->dropUnique('classroom_events_idempotency_key_unique');
            $table->dropColumn('idempotency_key');
        });
    }
};
