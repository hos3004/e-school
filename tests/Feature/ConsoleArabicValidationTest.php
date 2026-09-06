<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Modules\Identity\Domain\Models\User;
use Modules\Organization\Domain\Models\Organization;
use Tests\TestCase;

final class ConsoleArabicValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['console.enabled' => true]);
        $this->withoutVite();
        Http::preventStrayRequests();
        Http::fake(['api.pwnedpasswords.com/*' => Http::response('', 200)]);
        foreach (['admin.panel.access', 'student.create', 'staff.contract.update', 'student.view.any',
            'enrollment.create', 'group.manage', 'schedule.manage', 'organizations.view',
            'organizations.manage_settings', 'settings.manage', 'academic_calendars.view_any',
            'academic_calendars.create', 'program.manage', 'course.manage'] as $ability) {
            Gate::define($ability, static fn (): bool => true);
        }
        $org = Organization::factory()->create();
        $actor = User::factory()->inOrganization($org->id)->create(['locale' => 'en']);
        $this->actingAs($actor)->withSession(['locale' => 'fr']);
    }

    public function test_student_and_teacher_registration_errors_use_arabic_field_names_and_summary(): void
    {
        foreach (['students', 'teachers'] as $kind) {
            $response = $this->postJson('/manage/'.$kind, [
                'account_mode' => 'new', 'full_name' => '', 'email' => 'invalid',
                'password' => 'short', 'password_confirmation' => 'different',
                'date_of_birth' => '2099-01-01', 'locale' => 'ar', 'timezone' => 'unknown',
            ])->assertUnprocessable()->assertJsonValidationErrors(['full_name', 'email', 'password', 'date_of_birth', 'timezone']);
            $this->assertArabicErrors($response);
            self::assertSame('حقل الاسم الكامل مطلوب.', $response->json('errors.full_name.0'));
            self::assertStringContainsString('البريد الإلكتروني', $response->json('errors.email.0'));
            self::assertStringContainsString('تأكيد كلمة المرور غير مطابق.', implode(' ', $response->json('errors.password')));
            self::assertStringNotContainsString('today', $response->json('errors.date_of_birth.0'));
        }
    }

    public function test_registration_questions_and_placement_nested_errors_are_arabic(): void
    {
        $response = $this->postJson('/manage/registration/forms', [
            'title' => '', 'slug' => 'INVALID VALUE', 'is_active' => 'invalid',
            'questions' => [['question' => '', 'type' => 'invalid', 'options' => [],
                'is_required' => 'invalid', 'is_active' => true, 'is_filterable' => false]],
        ])->assertUnprocessable()->assertJsonValidationErrors(['title', 'slug', 'is_active', 'questions.0.question', 'questions.0.type', 'questions.0.is_required']);
        $this->assertArabicErrors($response);
        $response = $this->postJson('/manage/placement', [
            'application_ids' => ['invalid'], 'course_id' => 'invalid',
        ])->assertUnprocessable()->assertJsonValidationErrors(['application_ids.0', 'course_id', 'group_id', 'new_group_name']);
        $this->assertArabicErrors($response);
    }

    public function test_quran_schedule_and_course_hierarchy_errors_have_readable_labels(): void
    {
        $response = $this->postJson('/manage/quran/'.Str::ulid(), [
            'staff_profile_id' => 'invalid', 'duration_minutes' => 'invalid', 'interval_weeks' => 0,
            'timezone' => 'unknown', 'starts_on' => 'not-a-date',
            'weekly_slots' => [['weekday' => 9, 'start_time' => '99:80']],
        ])->assertUnprocessable()->assertJsonValidationErrors(['staff_profile_id', 'weekly_slots.0.weekday', 'weekly_slots.0.start_time']);
        $this->assertArabicErrors($response);
        $response = $this->postJson('/manage/groups', [
            'code' => 'group-valid', 'name' => ['ar' => 'مجموعة'], 'timezone' => 'unknown',
            'program_ids' => ['invalid'],
        ])->assertUnprocessable()->assertJsonValidationErrors(['program_ids.0', 'timezone']);
        $this->assertArabicErrors($response);
        $response = $this->postJson('/manage/courses/levels', [
            'code' => 'level-valid', 'name' => ['ar' => 'مستوى'], 'program_id' => (string) Str::ulid(),
        ])->assertUnprocessable()->assertJsonValidationErrors('program_id');
        $this->assertArabicErrors($response);
    }

    public function test_settings_rule_errors_and_account_profile_errors_stay_arabic_for_non_arabic_accounts(): void
    {
        foreach ([
            ['accounts', ['username_prefix' => 'INVALID', 'version' => 'old']],
            ['notifications', ['category' => 'session_changed', 'channels' => ['invalid'],
                'is_critical' => 'invalid', 'respects_quiet_hours' => true, 'version' => 'old']],
            ['calendar-create', ['name' => [], 'starts_on' => 'bad', 'ends_on' => 'bad']],
        ] as [$operation, $payload]) {
            $this->assertArabicErrors($this->postJson('/manage/settings/'.$operation, $payload)->assertUnprocessable());
        }
        $response = $this->patchJson('/learn/student/profile', ['name' => [], 'phone' => [], 'timezone' => []])
            ->assertUnprocessable()->assertJsonValidationErrors(['name', 'phone', 'timezone']);
        $this->assertArabicErrors($response);
        self::assertSame('en', auth()->user()->fresh()->locale);
    }

    public function test_arabic_catalog_covers_framework_rules_and_common_database_validation_messages(): void
    {
        $defaults = require base_path('vendor/laravel/framework/src/Illuminate/Translation/lang/en/validation.php');
        $arabic = require resource_path('lang/ar/validation.php');
        foreach (Arr::dot(Arr::except($defaults, ['custom', 'attributes'])) as $key => $message) {
            self::assertIsString(Arr::get($arabic, $key), 'Missing Arabic validation: '.$key);
            self::assertMatchesRegularExpression('/\p{Arabic}/u', Arr::get($arabic, $key));
        }
        app()->setLocale('ar');
        $validator = Validator::make(['username' => auth()->user()->username], ['username' => 'unique:users,username']);
        self::assertTrue($validator->fails());
        self::assertSame('قيمة اسم المستخدم مستخدمة بالفعل. اختر قيمة أخرى.', $validator->errors()->first('username'));
    }

    private function assertArabicErrors(TestResponse $response): void
    {
        $messages = Arr::flatten($response->json('errors'));
        self::assertNotEmpty($messages);
        foreach ([...$messages, $response->json('message')] as $message) {
            self::assertIsString($message);
            self::assertMatchesRegularExpression('/\p{Arabic}/u', $message);
            self::assertDoesNotMatchRegularExpression('/[A-Za-z_]/', $message, $message);
        }
    }
}
