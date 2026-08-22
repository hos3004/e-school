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

        $email = $this->option('email') ?? $this->ask('البريد الإلكتروني', 'admin@demo.local');
        $password = $this->option('password') ?? $this->secret('كلمة المرور') ?? 'password';
        $name = $this->option('name') ?? $this->ask('الاسم', 'Admin');

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

        $roleId = DB::table('roles')->where('name', 'platform_admin')->value('id');

        if ($roleId !== null) {
            DB::table('model_has_roles')->updateOrInsert(
                [
                    'role_id' => $roleId,
                    'model_type' => User::class,
                    'model_id' => $user->id,
                ],
            );
            $this->info('أُسند الدور platform_admin.');
        } else {
            $this->warn('الدور platform_admin غير موجود — لم يُسند أي دور.');
        }

        $this->info('Email: '.$user->email);
        $this->info('Password: '.$password);

        return self::SUCCESS;
    }
}
