<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Modules\Identity\Domain\Models\User;

final class CreateAdminUser extends Command
{
    protected $signature = 'eschool:admin
        {--email= : بريد المدير}
        {--password= : كلمة المرور}
        {--name= : اسم المدير}';

    protected $description = 'إنشاء مستخدم إداري للوحة Filament';

    public function handle(): int
    {
        $organizationId = DB::table('organizations')->orderBy('id')->value('id');

        if ($organizationId === null) {
            $this->error('لا توجد أي مؤسسة في قاعدة البيانات.');

            return self::FAILURE;
        }

        $email = (string) ($this->option('email') ?? $this->ask('البريد الإلكتروني'));
        $password = (string) ($this->option('password') ?? $this->secret('كلمة المرور (16 محرفًا على الأقل)'));
        $name = (string) ($this->option('name') ?? $this->ask('الاسم', 'Platform Administrator'));

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->error('البريد الإلكتروني غير صالح.');

            return self::FAILURE;
        }

        if (mb_strlen($password) < 16) {
            $this->error('كلمة المرور يجب أن تكون 16 محرفًا على الأقل ولا توجد قيمة افتراضية.');

            return self::FAILURE;
        }

        $roleId = DB::table('roles')->where('name', 'platform_admin')->value('id');

        if ($roleId === null) {
            $this->error('الدور platform_admin غير موجود — شغّل بذرة الصلاحيات المرجعية أولًا.');

            return self::FAILURE;
        }

        $user = User::query()->firstOrCreate(
            ['email' => $email],
            [
                'organization_id' => $organizationId,
                'name' => $name,
                'password' => Hash::make($password),
            ],
        );

        if (!$user->wasRecentlyCreated) {
            $this->warn('المستخدم موجود مسبقًا — سيُستخدم الحساب الحالي.');
        }

        DB::table('model_has_roles')->updateOrInsert(
            [
                'role_id' => $roleId,
                'model_type' => User::class,
                'model_id' => $user->id,
            ],
        );
        $this->info('أُسند الدور platform_admin.');

        $this->info('Email: '.$user->email);

        return self::SUCCESS;
    }
}
