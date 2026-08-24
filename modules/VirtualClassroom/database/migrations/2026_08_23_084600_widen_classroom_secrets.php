<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classrooms', function (Blueprint $table): void {
            // Laravel encrypted casts exceed varchar(255) for both provider secrets.
            $table->text('moderator_secret')->change();
            $table->text('attendee_secret')->change();
        });
    }

    public function down(): void
    {
        Schema::table('classrooms', function (Blueprint $table): void {
            $table->string('moderator_secret')->change();
            $table->string('attendee_secret')->change();
        });
    }
};
