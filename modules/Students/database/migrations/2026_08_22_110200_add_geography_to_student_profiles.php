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
        Schema::table('student_profiles', function (Blueprint $table): void {
            $table->char('country_id', 26)->nullable();
            $table->char('region_id', 26)->nullable();

            $table->foreign('country_id')->references('id')->on('countries')->nullOnDelete();
            $table->foreign('region_id')->references('id')->on('regions')->nullOnDelete();
            $table->index(['country_id', 'region_id']);
        });

        // إبقاء العمود القديم للتوافق، مع ترحيل قيمه إلى المرجع المنظّم حيثما أمكن.
        $countries = DB::table('countries')->pluck('id', 'iso2')->all();
        foreach ($countries as $iso2 => $countryId) {
            DB::table('student_profiles')
                ->where('country', $iso2)
                ->whereNull('country_id')
                ->update(['country_id' => $countryId]);
        }
    }

    public function down(): void
    {
        Schema::table('student_profiles', function (Blueprint $table): void {
            $table->dropForeign(['country_id']);
            $table->dropForeign(['region_id']);
            $table->dropColumn(['country_id', 'region_id']);
        });
    }
};
