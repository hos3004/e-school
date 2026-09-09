<?php

declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_profiles', function (Blueprint $table): void {
            $table->string('city')->nullable();
            $table->string('region_name')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('staff_profiles', function (Blueprint $table): void {
            $table->dropColumn(['city', 'region_name']);
        });
    }
};
