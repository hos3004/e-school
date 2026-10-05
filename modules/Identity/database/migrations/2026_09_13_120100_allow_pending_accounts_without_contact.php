<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * الحساب الذي ينشئه الإداري قد يبدأ بلا بريد ولا هاتف، لكن الحساب المكتمل
 * يظل ملزمًا بوسيلة تواصل واحدة على الأقل — فصفحة إكمال البيانات تفرض
 * البريد والهاتف معًا قبل أن يصير profile_completed_at غير فارغ.
 */
return new class extends Migration
{
    private const CONTACT = "email IS NOT NULL OR NULLIF(BTRIM(phone), '') IS NOT NULL";

    public function up(): void
    {
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_contact_required');
        DB::statement('ALTER TABLE users ADD CONSTRAINT users_contact_required CHECK ('.self::CONTACT.' OR profile_completed_at IS NULL)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_contact_required');
        DB::statement('ALTER TABLE users ADD CONSTRAINT users_contact_required CHECK ('.self::CONTACT.')');
    }
};
