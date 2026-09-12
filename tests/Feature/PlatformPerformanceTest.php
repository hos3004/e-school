<?php

declare(strict_types=1);

use App\Support\PageTranslations;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Modules\Identity\Domain\Models\User;

it('only shares the login section and keeps its translations during partial navigation', function (): void {
    $this->withoutVite();
    config(['console.enabled' => true]);
    $this->get('/login')->assertOk()->assertInertia(fn (Assert $page): Assert => $page
        ->component('Auth/LearningLogin')
        ->where('translations', function ($lines): bool {
            $lines = collect($lines);

            return $lines->has('learning.entry.welcome')
                && !$lines->keys()->contains(fn ($key): bool => str_starts_with($key, 'console.'))
                && !$lines->keys()->contains(fn ($key): bool => str_starts_with($key, 'marketing.'))
                && strlen(json_encode($lines, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)) < 100_000;
        }));
    $this->get('/login', [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => Inertia\Inertia::getVersion(),
        'X-Inertia-Partial-Component' => 'Auth/LearningLogin',
        'X-Inertia-Partial-Data' => 'locale',
    ])->assertOk()->assertJsonMissingPath('props.translations');
});

it('supports a web session on the notification API without relying on a cached auth guard', function (): void {
    $user = User::factory()->create();
    $sessionKey = Auth::guard('web')->getName();
    config(['sanctum.stateful' => ['telecourse.org']]);
    $this->withSession([$sessionKey => $user->id]);
    Auth::forgetGuards();
    $this->getJson('/api/notifications/unread-count', ['Referer' => 'https://telecourse.org/learn/student'])
        ->assertOk()->assertJsonPath('data.unread_count', 0);

    $this->flushSession();
    Auth::forgetGuards();
    $this->getJson('/api/notifications/unread-count', ['Referer' => 'https://telecourse.org/learn/student'])
        ->assertUnauthorized();
});

it('does not classify a foreign origin as a stateful frontend', function (): void {
    config(['sanctum.stateful' => ['telecourse.org']]);
    expect(EnsureFrontendRequestsAreStateful::fromFrontend(
        Request::create('/api/notifications/unread-count', server: ['HTTP_REFERER' => 'https://untrusted.example/']),
    ))->toBeFalse();
});

it('includes the configured application origin despite a stale explicit Sanctum environment', function (): void {
    config(['app.url' => 'https://telecourse.org']);
    $sanctum = require config_path('sanctum.php');
    config(['sanctum.stateful' => $sanctum['stateful']]);
    expect(EnsureFrontendRequestsAreStateful::fromFrontend(
        Request::create('/api/notifications/unread-count', server: ['HTTP_REFERER' => 'https://telecourse.org/learn/teacher']),
    ))->toBeTrue();
});

it('keeps section dependencies and locale fallback available', function (string $component, string $key): void {
    $translations = app(PageTranslations::class)->forComponent($component, 'fr');
    expect($translations)->toHaveKey($key)->and($translations[$key])->not->toBe($key);
})->with([
    ['Marketing/Home', 'marketing.not_found.title'],
    ['Auth/Login', 'auth.login.title'],
    ['Auth/RegisterStudent', 'public_registration.brand'],
    ['Learning/Dashboard', 'learning.brand'],
    ['Learning/Notifications', 'notifications.bell.title'],
    ['Console/Sessions', 'console_sessions.title'],
    ['Console/Notifications', 'learning.brand'],
    ['Learning/Profile', 'console_people.columns.is_primary'],
    ['Teacher/Dashboard', 'teacher.dashboard.title'],
]);
