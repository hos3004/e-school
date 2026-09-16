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
        Schema::create('reporting_program_digest_recipients', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26);
            $table->char('program_id', 26)->nullable();
            $table->string('recipient_type', 32);
            $table->char('recipient_user_id', 26)->nullable();
            $table->string('custom_email', 255)->nullable();
            // قفل تفاؤلي صريح: `updated_at` بدقة ثانية واحدة (timestampsTz
            // الافتراضي) قد يتطابق بين حفظين متتاليين لنفس الثانية، فيُبطل
            // فحص الإصدار المتزامن صامتًا. عدّاد صحيح لا يتكرر أبدًا.
            $table->unsignedBigInteger('lock_version')->default(1);
            $table->timestampsTz();

            $table->foreign('organization_id')->references('id')->on('organizations')->restrictOnDelete();
            $table->foreign('recipient_user_id')->references('id')->on('users')->restrictOnDelete();

            // سطر واحد لكل تخصيص برنامج (program_id غير فارغ).
            $table->unique(['organization_id', 'program_id'], 'reporting_digest_recipients_org_program_unique');
            $table->index(['organization_id', 'program_id']);
        });

        /*
         * PostgreSQL يعامل كل NULL كقيمة مستقلة في UNIQUE العادي، فقيد
         * الأعلى وحده لا يمنع أكثر من سطر عام (program_id فارغ) لنفس
         * المؤسسة. فهرس جزئي صريح هو الضامن الحقيقي للسطر العام الواحد —
         * وهو الإعداد الأدنى المطلوب من العميل.
         */
        DB::statement(
            'create unique index reporting_digest_recipients_org_global_unique '.
            'on reporting_program_digest_recipients (organization_id) '.
            'where program_id is null',
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('reporting_program_digest_recipients');
    }
};
