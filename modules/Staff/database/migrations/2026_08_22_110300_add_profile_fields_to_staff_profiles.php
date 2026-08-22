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
            // Nullable فقط لترحيل السجلات القديمة دون اختلاق بيانات شخصية؛ الإدخالات الجديدة تتحقق منها طبقة الطلب.
            $table->enum('gender', ['male', 'female'])->nullable()->after('employment_type');
            $table->foreignUlid('country_id')->nullable()->after('gender')->constrained('countries')->nullOnDelete();
            $table->foreignUlid('region_id')->nullable()->after('country_id')->constrained('regions')->nullOnDelete();
            $table->date('date_of_birth')->nullable()->after('region_id');
            $table->string('phone', 32)->nullable()->after('date_of_birth');

            $table->index('country_id');
            $table->index('region_id');
        });
    }

    public function down(): void
    {
        Schema::table('staff_profiles', function (Blueprint $table): void {
            $table->dropIndex(['country_id']);
            $table->dropIndex(['region_id']);
            $table->dropForeign(['country_id']);
            $table->dropForeign(['region_id']);
            $table->dropColumn(['gender', 'country_id', 'region_id', 'date_of_birth', 'phone']);
        });
    }
};
