<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * من يصل إلى البوت، وكم يكلّف.
 *
 * **الوصول**: صفّ هنا هو استثناء صريح على ما يمنحه الدور. غياب الصف يعني
 * «اتبع الافتراضي» — وهو نمط notification_preferences نفسه. السبب أن الصلاحيات
 * جمعية بطبيعتها: منحُ دورٍ ما صلاحيةً لا يمكن نقضه لشخص واحد عبر الصلاحيات،
 * فلزم جدول استثناءات صريح ليقدر الأدمن على إغلاق البوت لحساب بعينه.
 *
 * **الاستهلاك**: صف لكل مستخدم في كل يوم. مجموع اليوم للمؤسسة يُحسب بالجمع، لا
 * بصف مجمَّع، تفاديًا لسباق التحديث على صفٍّ واحد يتنافس عليه كل المستخدمين.
 * التكلفة بوحدات صغرى صحيحة — لا float في أي حساب مال في هذا المشروع.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_bot_account_access', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26);
            $table->char('user_id', 26);

            $table->boolean('enabled');
            $table->text('reason');

            $table->char('updated_by', 26)->nullable();
            $table->timestampsTz();

            $table->foreign('organization_id')->references('id')->on('organizations')->restrictOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();

            $table->unique(['organization_id', 'user_id'], 'support_bot_account_access_unique');
        });

        Schema::create('support_bot_usage', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26);
            $table->char('user_id', 26);

            $table->date('usage_date');

            $table->unsignedInteger('requests')->default(0);
            $table->unsignedBigInteger('input_tokens')->default(0);
            $table->unsignedBigInteger('output_tokens')->default(0);

            // ميكرو-دولار، عدد صحيح. للعدّاد والسقف اليومي فقط، لا للفوترة.
            $table->unsignedBigInteger('cost_micro_usd')->default(0);

            $table->timestampsTz();

            $table->foreign('organization_id')->references('id')->on('organizations')->restrictOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();

            $table->unique(['organization_id', 'user_id', 'usage_date'], 'support_bot_usage_unique');
            $table->index(['organization_id', 'usage_date'], 'support_bot_usage_period_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_bot_usage');
        Schema::dropIfExists('support_bot_account_access');
    }
};
