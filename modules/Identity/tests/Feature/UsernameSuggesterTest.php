<?php

declare(strict_types=1);

namespace Modules\Identity\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Identity\Application\Services\UsernameSuggester;
use Shared\Testing\Fixtures;
use Tests\TestCase;

final class UsernameSuggesterTest extends TestCase
{
    use RefreshDatabase;

    public function test_username_suggester_generates_unique_latin_usernames_from_arabic_name(): void
    {
        $suggester = app(UsernameSuggester::class);
        $suggestions = $suggester->suggest('أحمد محمد العلي');

        $this->assertCount(3, $suggestions);
        $this->assertSame('student.ahmed', $suggestions[0]);
        foreach ($suggestions as $username) {
            $this->assertStringStartsWith('student.', $username);
            $this->assertMatchesRegularExpression('/^[a-z0-9\.]+$/', $username);
            $this->assertFalse(DB::table('users')->where('username', $username)->exists());
        }
    }

    public function test_username_suggester_skips_existing_usernames(): void
    {
        $organizationId = Fixtures::organizationId();

        DB::table('users')->insert([
            'id' => (string) Str::ulid(),
            'organization_id' => $organizationId,
            'name' => 'Existing User',
            'email' => 'existing@test.local',
            'username' => 'student.ahmed',
            'password' => 'password',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $suggester = app(UsernameSuggester::class);
        $suggestions = $suggester->suggest('أحمد علي');

        $this->assertNotContains('student.ahmed', $suggestions);
        $this->assertCount(3, array_unique($suggestions));
    }

    public function test_username_suggester_uses_the_organization_prefix_and_rejects_reserved_names(): void
    {
        $organizationId = Fixtures::organizationId();

        DB::table('organization_settings')->insert([
            'id' => (string) Str::ulid(),
            'organization_id' => $organizationId,
            'key' => (string) config('admission.username.organization_setting_key'),
            'value' => json_encode('academy', JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $suggestions = app(UsernameSuggester::class)->suggest('admin', $organizationId);

        $this->assertCount(3, $suggestions);
        $this->assertContainsOnly('string', $suggestions);
        $this->assertStringStartsWith('academy.', $suggestions[0]);

        foreach ((array) config('admission.username.reserved', []) as $reserved) {
            $this->assertNotContains($reserved, $suggestions);
        }
    }
}
