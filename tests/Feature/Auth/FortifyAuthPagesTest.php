<?php

declare(strict_types=1);

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Modules\Identity\Database\Factories\UserFactory;
use Modules\Identity\Domain\Models\User;

/*
 * قبول تدفق المصادقة عبر Fortify على واجهات Inertia:
 * دخول بمعرّف موحد (اسم مستخدم/بريد/هاتف)، رفض الحسابات غير النشطة،
 * حدود المحاولات، استعادة البريد كاملة برسالة موحدة تمنع تعداد الحسابات،
 * وتوثيق آخر دخول عند النجاح.
 */

function authTestOrganizationId(): string
{
    $id = (string) Str::ulid();

    DB::table('organizations')->insert([
        'id' => $id,
        'name' => json_encode(['ar' => 'مدرسة المصادقة', 'en' => 'Auth School'], JSON_THROW_ON_ERROR),
        'slug' => 'auth-'.strtolower((string) Str::ulid()),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $id;
}

it('renders the inertia login page with the unified identifier field', function (): void {
    $this->get('/login')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Auth/Login')
            ->where('action', route('login')));
});

it('authenticates an active user by username and records the login', function (): void {
    UserFactory::new()->inOrganization(authTestOrganizationId())->create([
        'username' => 'portal.login',
        'email' => 'portal.login@example.test',
    ]);

    $response = $this->post('/login', [
        'login' => 'portal.login',
        'password' => 'password',
    ]);

    $this->assertAuthenticated();

    expect($response->status())->toBeIn([200, 302]);

    $user = User::query()->where('username', 'portal.login')->firstOrFail();

    expect($user->last_login_at)->not->toBeNull();
});

it('authenticates by email address as well as username', function (): void {
    UserFactory::new()->inOrganization(authTestOrganizationId())->create([
        'username' => 'by.email',
        'email' => 'by.email@example.test',
    ]);

    $this->post('/login', [
        'login' => 'by.email@example.test',
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
});

it('authenticates by phone number when it matches E.164 storage', function (): void {
    UserFactory::new()->inOrganization(authTestOrganizationId())->create([
        'username' => 'by.phone',
        'email' => null,
        'phone' => '+201000111222',
        'phone_country' => 'EG',
    ]);

    $this->post('/login', [
        'login' => '+201000111222',
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
});

it('rejects a wrong password without revealing whether the account exists', function (): void {
    UserFactory::new()->inOrganization(authTestOrganizationId())->create([
        'username' => 'wrong.pass',
    ]);

    $response = $this->from('/login')->post('/login', [
        'login' => 'wrong.pass',
        'password' => 'definitely-wrong-1',
    ]);

    $this->assertGuest();
    $response->assertSessionHasErrors('login');
});

it('refuses suspended accounts even with correct credentials', function (): void {
    UserFactory::new()->suspended()->inOrganization(authTestOrganizationId())->create([
        'username' => 'suspended.user',
    ]);

    $this->post('/login', [
        'login' => 'suspended.user',
        'password' => 'password',
    ]);

    $this->assertGuest();
});

it('locks the login identifier after five failed attempts in fifteen minutes', function (): void {
    UserFactory::new()->inOrganization(authTestOrganizationId())->create([
        'username' => 'throttled.user',
    ]);

    foreach (range(1, 5) as $attempt) {
        $this->post('/login', [
            'login' => 'throttled.user',
            'password' => "bad-password-{$attempt}",
        ]);
    }

    $this->assertGuest();

    $response = $this->post('/login', [
        'login' => 'throttled.user',
        'password' => 'password',
    ]);

    expect($response->status())->toBe(429);
});

it('renders the forgot password page', function (): void {
    $this->get('/forgot-password')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Auth/ForgotPassword'));
});

it('sends a reset link for an existing email', function (): void {
    Notification::fake();

    $user = UserFactory::new()->inOrganization(authTestOrganizationId())->create([
        'username' => 'reset.me',
        'email' => 'reset.me@example.test',
    ]);

    $this->post('/forgot-password', ['email' => 'reset.me@example.test'])
        ->assertSessionHas('status');

    Notification::assertSentTo($user, ResetPassword::class);
});

it('returns the identical neutral status for unknown emails to prevent enumeration', function (): void {
    Notification::fake();

    UserFactory::new()->inOrganization(authTestOrganizationId())->create([
        'username' => 'known.mail',
        'email' => 'known@example.test',
    ]);

    // البريد غير الموجود أولًا: يجب أن يصل رد محايد بلا إرسال شيء.
    $unknownStatus = $this->post('/forgot-password', ['email' => 'ghost@example.test'])
        ->assertSessionHas('status')
        ->assertSessionHasNoErrors()
        ->baseResponse
        ->getSession()
        ->get('status');

    Notification::assertNothingSent();

    // نفس الطلب على بريد موجود: نفس الرسالة تمامًا مع الإرسال الفعلي.
    $knownStatus = $this->post('/forgot-password', ['email' => 'known@example.test'])
        ->assertSessionHas('status')
        ->baseResponse
        ->getSession()
        ->get('status');

    expect($unknownStatus)->toBe($knownStatus);

    Notification::assertSentTo(
        User::query()->where('email', 'known@example.test')->firstOrFail(),
        ResetPassword::class,
    );
});

it('completes the full email reset flow and allows signing in with the new password', function (): void {
    $user = UserFactory::new()->inOrganization(authTestOrganizationId())->create([
        'username' => 'full.reset',
        'email' => 'full.reset@example.test',
    ]);

    $token = app('auth.password.broker')->createToken($user);

    $this->get('/reset-password/'.$token)
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Auth/ResetPassword')
            ->where('token', $token));

    $this->post('/reset-password', [
        'token' => $token,
        'email' => 'full.reset@example.test',
        'password' => 'New-Secret-2026',
        'password_confirmation' => 'New-Secret-2026',
    ])->assertSessionHasNoErrors();

    expect(Hash::check('New-Secret-2026', (string) $user->fresh()->getAuthPassword()))->toBeTrue();

    $this->post('/login', [
        'login' => 'full.reset@example.test',
        'password' => 'New-Secret-2026',
    ]);

    $this->assertAuthenticated();
});

it('rejects a reset with an invalid token and keeps the old password', function (): void {
    $user = UserFactory::new()->inOrganization(authTestOrganizationId())->create([
        'username' => 'bad.token',
        'email' => 'bad.token@example.test',
    ]);

    $this->post('/reset-password', [
        'token' => 'not-a-real-token',
        'email' => 'bad.token@example.test',
        'password' => 'Another-Secret-9',
        'password_confirmation' => 'Another-Secret-9',
    ])->assertSessionHasErrors();

    expect(Hash::check('password', (string) $user->fresh()->getAuthPassword()))->toBeTrue();
});
