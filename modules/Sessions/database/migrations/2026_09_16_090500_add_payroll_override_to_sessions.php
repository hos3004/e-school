<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sessions', function (Blueprint $table): void {
            $table->boolean('payroll_exempt')->default(false)->after('makeup_for_session_id');
            $table->bigInteger('payroll_rate_override_minor_units')->nullable()->after('payroll_exempt');
        });
    }

    public function down(): void
    {
        Schema::table('sessions', function (Blueprint $table): void {
            $table->dropColumn(['payroll_exempt', 'payroll_rate_override_minor_units']);
        });
    }
};
