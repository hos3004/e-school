<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // الإصدارات النظيفة من مخطط Identity تحتوي هذه الأعمدة أصلًا؛
        // الشروط تجعل الهجرة جسرًا آمنًا لقواعد أقدم دون إعادة إضافة العمود.
        if (!Schema::hasColumn('users', 'username')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('username', 64)->nullable()->after('name');
            });
        }

        if (!Schema::hasColumn('users', 'phone')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('phone', 32)->nullable()->after('email');
            });
        }

        if (!Schema::hasColumn('users', 'phone_verified_at')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->timestampTz('phone_verified_at')->nullable()->after('phone');
            });
        }

        $existingUsers = DB::table('users')
            ->whereNull('username')
            ->orderBy('id')
            ->get(['id', 'email', 'name']);
        $usedUsernames = DB::table('users')
            ->whereNotNull('username')
            ->pluck('username')
            ->filter(static fn (mixed $username): bool => is_string($username))
            ->map(static fn (string $username): string => strtolower($username))
            ->all();
        $reserved = array_map(
            static fn (mixed $username): string => strtolower((string) $username),
            (array) config('admission.username.reserved', []),
        );

        foreach ($existingUsers as $user) {
            $emailLocalPart = is_string($user->email) && str_contains($user->email, '@')
                ? Str::before($user->email, '@')
                : '';
            $base = strtolower(Str::slug(Str::ascii($emailLocalPart), ''));

            if ($base === '' && is_string($user->name)) {
                $base = strtolower(Str::slug(Str::ascii($user->name), ''));
            }

            $base = $base !== '' ? mb_substr($base, 0, 56) : 'user';

            if (in_array($base, $reserved, true)) {
                $base .= 'user';
            }

            $candidate = $base;
            $counter = 1;

            while (in_array(strtolower($candidate), $usedUsernames, true)) {
                $candidate = mb_substr($base, 0, 56).$counter;
                $counter++;
            }

            $usedUsernames[] = strtolower($candidate);
            DB::table('users')->where('id', $user->id)->update(['username' => $candidate]);
        }

        DB::statement('ALTER TABLE users ALTER COLUMN username TYPE citext USING username::citext');
        // مسارات التطبيق تطلب username صراحةً؛ الافتراضي يحمي فقط البذور
        // والـfixtures القديمة التي تكتب SQL مباشرًا أثناء الانتقال.
        DB::statement("ALTER TABLE users ALTER COLUMN username SET DEFAULT ('user.' || replace(gen_random_uuid()::text, '-', ''))");
        DB::statement('ALTER TABLE users ALTER COLUMN username SET NOT NULL');
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS users_username_unique ON users (username) WHERE deleted_at IS NULL');
        DB::statement('ALTER TABLE users ALTER COLUMN email DROP NOT NULL');
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_contact_required');
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_contact_required CHECK (email IS NOT NULL OR NULLIF(BTRIM(phone), '') IS NOT NULL)");
    }

    public function down(): void
    {
        // الأعمدة جزء من مخطط الإنشاء الحالي؛ التراجع يعكس فقط قيد هذه الهجرة
        // ولا يحذف بيانات هوية قد تكون استُخدمت قبل تطبيقها.
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_contact_required');
        DB::statement('ALTER TABLE users ALTER COLUMN username DROP DEFAULT');
        DB::statement('ALTER TABLE users ALTER COLUMN username DROP NOT NULL');
    }
};
