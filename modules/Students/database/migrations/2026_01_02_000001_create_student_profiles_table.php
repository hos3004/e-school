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
        Schema::create('student_profiles', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26);
            $table->char('user_id', 26)->unique();
            $table->string('student_code')->unique();
            $table->date('date_of_birth')->nullable();
            $table->string('gender')->nullable();
            $table->char('nationality', 2)->nullable();
            $table->char('country', 2)->nullable();
            $table->string('city')->nullable();
            $table->string('preferred_language')->nullable();
            $table->date('joined_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['organization_id', 'student_code']);

            $table->foreign('organization_id')->references('id')->on('organizations')->restrictOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
        });

        DB::statement(
            'CREATE INDEX student_profiles_name_search_idx ON users USING gin (to_tsvector(\'simple\', (name)::text))',
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS student_profiles_name_search_idx');
        Schema::dropIfExists('student_profiles');
    }
};
