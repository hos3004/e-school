<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registration_applications', function (Blueprint $table): void {
            // المعرّف فقط؛ التحقق من ملكية الكورس يتم عبر عقد Academics العام.
            $table->char('preferred_course_id', 26)->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('registration_applications', function (Blueprint $table): void {
            $table->dropColumn('preferred_course_id');
        });
    }
};
