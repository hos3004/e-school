<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE postponement_requests ALTER COLUMN requested_for_student_id DROP NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE postponement_requests ALTER COLUMN requested_for_student_id SET NOT NULL');
    }
};
