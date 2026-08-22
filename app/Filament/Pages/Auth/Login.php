<?php

declare(strict_types=1);

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\Login as BaseLogin;

/**
 * صفحة دخول أسرع.
 *
 * فرقان عن الافتراضية:
 *  - «تذكّرني» مفعّلة مسبقًا — مستخدمو اللوحة يدخلون يوميًا من نفس الجهاز،
 *    وإجبارهم على تسجيل دخول متكرر لا يضيف أمنًا حقيقيًا.
 *  - في بيئة التطوير تُملأ بيانات الحساب التجريبي تلقائيًا حتى لا يضيع
 *    وقت المراجعة في كتابتها. لا يحدث هذا في الإنتاج إطلاقًا.
 */
final class Login extends BaseLogin
{
    public function mount(): void
    {
        parent::mount();

        if (!app()->environment('local')) {
            return;
        }

        $this->form->fill([
            'email' => 'admin@demo.local',
            'password' => 'Password123!',
            'remember' => true,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getCredentialsFromFormData(array $data): array
    {
        return [
            'email' => $data['email'],
            'password' => $data['password'],
        ];
    }
}
