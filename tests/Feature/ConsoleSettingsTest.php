<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Identity\Domain\Contracts\UsernameSuggestionGateway;
use Modules\Identity\Domain\Models\User;
use Modules\Notifications\Application\Services\NotificationCategorySettingsResolver;
use Modules\Notifications\Domain\Models\NotificationCategorySetting;
use Modules\Organization\Application\Actions\AddHoliday;
use Modules\Organization\Application\Actions\CreateAcademicCalendar;
use Modules\Organization\Domain\Contracts\OrganizationSettingQueries;
use Modules\Organization\Domain\Models\AcademicCalendar;
use Modules\Organization\Domain\Models\Holiday;
use Modules\Organization\Domain\Models\Organization;
use Tests\TestCase;

final class ConsoleSettingsTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private array $permissions = [
        'admin.panel.access', 'organizations.view', 'organizations.manage_settings', 'settings.manage',
        'academic_calendars.view_any', 'academic_calendars.create', 'academic_calendars.activate', 'academic_calendars.close',
        'holidays.view_any', 'holidays.create', 'holidays.delete',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        config(['console.enabled' => true]);
        $this->withoutVite();
        foreach ($this->permissions as $permission) {
            Gate::define($permission, fn (): bool => in_array($permission, $this->permissions, true));
        }
    }

    public function test_opening_settings_does_not_create_configuration_and_readers_cannot_write(): void
    {
        [$organization, $actor] = $this->context();
        $this->permissions = ['admin.panel.access', 'organizations.view'];
        $this->actingAs($actor)->get('/manage/settings')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('accounts', null)->has('notificationCategories', 0)->has('calendars', 0)->has('holidays', 0));
        foreach (['accounts', 'notifications', 'calendar-create', 'calendar-activate', 'calendar-close', 'holiday-create', 'holiday-remove'] as $operation) {
            $this->post('/manage/settings/'.$operation, [])->assertForbidden();
        }
        self::assertSame(0, DB::table('organization_settings')->where('organization_id', $organization->id)->count());
        self::assertSame(0, NotificationCategorySetting::query()->count());
    }

    public function test_prefix_changes_real_suggestions_only_for_its_organization_and_rejects_stale_saves(): void
    {
        [$organization, $actor] = $this->context();
        [$foreign] = $this->context();
        $this->actingAs($actor);
        $accounts = $this->props()['accounts'];
        $payload = [
            'username_prefix' => 'tele', 'version' => $accounts['version'],
            'organization_id' => $foreign->id, 'key' => 'payroll', 'value' => true,
        ];
        $this->post('/manage/settings/accounts', $payload)->assertRedirect(route('console.settings'))->assertSessionHasNoErrors();
        self::assertSame('tele', app(OrganizationSettingQueries::class)->value($organization->id, 'username_prefix'));
        self::assertNull(app(OrganizationSettingQueries::class)->value($foreign->id, 'username_prefix'));
        self::assertStringStartsWith('tele.', app(UsernameSuggestionGateway::class)->suggest('أحمد علي', $organization->id)[0]);
        self::assertStringNotContainsString('tele.', app(UsernameSuggestionGateway::class)->suggest('أحمد علي', $foreign->id)[0]);
        $this->post('/manage/settings/accounts', [...$payload, 'username_prefix' => 'new'])->assertSessionHasErrors('version');
        $fresh = $this->props()['accounts'];
        $this->post('/manage/settings/accounts', ['username_prefix' => '', 'version' => $fresh['version']])->assertSessionHasNoErrors();
        self::assertNull(app(OrganizationSettingQueries::class)->value($organization->id, 'username_prefix'));
        self::assertSame(2, DB::table('audit_log')->where('action', 'console.settings.accounts')->count());
        self::assertSame($actor->username, $actor->fresh()->username);
    }

    public function test_notification_settings_control_the_existing_resolver_and_keep_other_organizations_unchanged(): void
    {
        [$organization, $actor] = $this->context();
        [$foreign] = $this->context();
        $this->actingAs($actor);
        $row = collect($this->props()['notificationCategories'])->firstWhere('category', 'session_changed');
        $payload = [
            'category' => 'session_changed', 'channels' => ['in_app'], 'is_critical' => false,
            'respects_quiet_hours' => true, 'version' => $row['version'], 'organization_id' => $foreign->id,
        ];
        $this->post('/manage/settings/notifications', $payload)->assertSessionHasNoErrors()->assertRedirect(route('console.settings'));
        $resolver = new NotificationCategorySettingsResolver;
        self::assertSame(['in_app'], $resolver->channels($organization->id, 'session_changed'));
        self::assertFalse($resolver->isCritical($organization->id, 'session_changed'));
        self::assertSame(config('notifications.categories.session_changed.channels'), $resolver->channels($foreign->id, 'session_changed'));
        self::assertSame(1, NotificationCategorySetting::query()->count());
        $this->post('/manage/settings/notifications', [...$payload, 'channels' => ['email']])->assertSessionHasErrors('version');
        $this->post('/manage/settings/notifications', [...$payload, 'channels' => ['unsafe']])->assertSessionHasErrors('channels.0');
        $this->post('/manage/settings/notifications', [...$payload, 'category' => 'new-category'])->assertSessionHasErrors('category');
        self::assertTrue(DB::table('audit_log')->where('action', 'console.settings.notifications')->where('organization_id', $organization->id)->exists());
    }

    public function test_calendar_creation_activation_closing_and_holiday_validation_use_existing_actions(): void
    {
        [$organization, $actor] = $this->context();
        $this->actingAs($actor);
        $this->post('/manage/settings/calendar-create', ['name' => 'العام الدراسي', 'starts_on' => '2027-01-01', 'ends_on' => '2027-12-31'])
            ->assertSessionHasNoErrors()->assertRedirect(route('console.settings'));
        $calendar = AcademicCalendar::query()->sole();
        self::assertSame(['ar' => 'العام الدراسي'], $calendar->name);
        self::assertFalse($calendar->is_active);
        $row = $this->props()['calendars'][0];
        $this->post('/manage/settings/calendar-activate', ['id' => $row['id'], 'version' => $row['version']])->assertSessionHasNoErrors();
        self::assertTrue($calendar->fresh()->is_active);
        $this->post('/manage/settings/calendar-create', ['name' => 'متداخل', 'starts_on' => '2027-04-01', 'ends_on' => '2027-09-30'])
            ->assertSessionHasErrors('settings');
        $this->post('/manage/settings/holiday-create', [
            'name' => 'عطلة الربيع', 'starts_on' => '2027-03-01', 'ends_on' => '2027-03-02',
            'academic_calendar_id' => $calendar->id, 'blocks_scheduling' => true,
        ])->assertSessionHasNoErrors();
        self::assertTrue(Holiday::query()->sole()->blocks_scheduling);
        $this->post('/manage/settings/holiday-create', [
            'name' => 'عطلة متداخلة', 'starts_on' => '2027-03-02', 'ends_on' => '2027-03-03',
            'academic_calendar_id' => $calendar->id, 'blocks_scheduling' => true,
        ])->assertSessionHasErrors('settings');
        $row = $this->props()['calendars'][0];
        $this->post('/manage/settings/calendar-close', ['id' => $row['id'], 'version' => $row['version']])->assertSessionHasNoErrors();
        self::assertFalse($calendar->fresh()->is_active);
        $holiday = $this->props()['holidays'][0];
        $this->post('/manage/settings/holiday-remove', ['id' => $holiday['id'], 'version' => $holiday['version']])->assertSessionHasNoErrors();
        self::assertSame(0, Holiday::query()->count());
        self::assertTrue(DB::table('audit_log')->where('action', 'console.settings.holiday-remove')->where('auditable_id', $holiday['id'])->exists());
    }

    public function test_foreign_calendar_and_holiday_ids_cannot_be_read_or_modified(): void
    {
        [$organization, $actor] = $this->context();
        [$foreign] = $this->context();
        $calendar = app(CreateAcademicCalendar::class)->execute($foreign, ['ar' => 'تقويم مؤسسة أخرى'], '2027-01-01', '2027-12-31');
        $holiday = app(AddHoliday::class)->execute($foreign->id, ['ar' => 'عطلة خاصة'], '2027-03-01', '2027-03-02', $calendar->id);
        $this->actingAs($actor);
        $props = $this->props();
        self::assertSame([], $props['calendars']);
        self::assertSame([], $props['holidays']);
        $this->post('/manage/settings/calendar-activate', ['id' => $calendar->id, 'version' => str_repeat('0', 64)])->assertNotFound();
        $this->post('/manage/settings/holiday-remove', ['id' => $holiday->id, 'version' => str_repeat('0', 64)])->assertNotFound();
        $this->post('/manage/settings/holiday-create', [
            'name' => 'عطلة', 'starts_on' => '2027-04-01', 'ends_on' => '2027-04-02',
            'academic_calendar_id' => $calendar->id, 'blocks_scheduling' => true,
        ])->assertNotFound();
        self::assertSame(0, Holiday::query()->forOrganization($organization->id)->count());
        self::assertFalse($calendar->fresh()->is_active);
    }

    public function test_reactivating_a_calendar_does_not_duplicate_inherited_holidays(): void
    {
        [$organization, $actor] = $this->context();
        $calendar = app(CreateAcademicCalendar::class)->execute($organization, ['ar' => 'تقويم الدراسة'], '2027-01-01', '2027-12-31');
        app(AddHoliday::class)->execute($organization->id, ['ar' => 'عطلة عامة'], '2027-04-01', '2027-04-02');
        $this->actingAs($actor);
        foreach (['calendar-activate', 'calendar-close', 'calendar-activate'] as $operation) {
            $row = $this->props()['calendars'][0];
            $this->post('/manage/settings/'.$operation, ['id' => $row['id'], 'version' => $row['version']])->assertSessionHasNoErrors();
        }
        self::assertSame(1, Holiday::query()->where('academic_calendar_id', $calendar->id)->count());
        self::assertSame(2, Holiday::query()->forOrganization($organization->id)->count());
    }

    public function test_stale_calendar_activation_cannot_replace_a_more_recent_active_calendar(): void
    {
        [$organization, $actor] = $this->context();
        $first = app(CreateAcademicCalendar::class)->execute($organization, ['ar' => 'الفترة الأولى'], '2027-01-01', '2027-12-31');
        $second = app(CreateAcademicCalendar::class)->execute($organization, ['ar' => 'الفترة الثانية'], '2028-01-01', '2028-12-31');
        $this->actingAs($actor);
        $rows = collect($this->props()['calendars'])->keyBy('id');
        $this->post('/manage/settings/calendar-activate', ['id' => $first->id, 'version' => $rows[$first->id]['version']])->assertSessionHasNoErrors();
        $this->post('/manage/settings/calendar-activate', ['id' => $second->id, 'version' => $rows[$second->id]['version']])->assertSessionHasErrors('version');
        self::assertTrue($first->fresh()->is_active);
        self::assertFalse($second->fresh()->is_active);
        self::assertSame(1, DB::table('audit_log')->where('action', 'console.settings.calendar-activate')->where('organization_id', $organization->id)->count());
        $fresh = collect($this->props()['calendars'])->firstWhere('id', $second->id);
        $this->post('/manage/settings/calendar-activate', ['id' => $second->id, 'version' => $fresh['version']])->assertSessionHasNoErrors();
        self::assertFalse($first->fresh()->is_active);
        self::assertTrue($second->fresh()->is_active);
    }

    /** @return array{Organization, User} */
    private function context(): array
    {
        $organization = Organization::factory()->create(['default_timezone' => 'Africa/Cairo']);
        $actor = User::factory()->inOrganization($organization->id)->create();

        return [$organization, $actor];
    }

    /** @return array<string, mixed> */
    private function props(): array
    {
        return $this->get('/manage/settings')->assertOk()->viewData('page')['props'];
    }
}
