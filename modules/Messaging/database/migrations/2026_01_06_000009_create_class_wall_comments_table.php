<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_wall_comments', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26);
            $table->char('post_id', 26);
            $table->char('user_id', 26);
            $table->text('body');
            $table->timestampTz('created_at')->nullable();
            $table->softDeletesTz();

            $table->index('organization_id', 'class_wall_comments_organization_id_index');
            $table->index('post_id', 'class_wall_comments_post_id_index');
            $table->index('user_id', 'class_wall_comments_user_id_index');

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->restrictOnDelete();
            $table->foreign('post_id')
                ->references('id')
                ->on('class_wall_posts')
                ->restrictOnDelete();
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_wall_comments');
    }
};
