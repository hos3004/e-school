<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Modules\Identity\Domain\Models\User;
use Modules\Organization\Domain\Models\Organization;
use Modules\Scheduling\Presentation\Filament\Resources\ScheduleResource;

uses(RefreshDatabase::class);

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

it('renders the real schedule form and operations hub while isolating organizations', function (): void {
    CarbonImmutable::setTestNow('2026-10-10 08:00:00 UTC');
    Gate::before(static fn (): bool => true);
    Filament::setCurrentPanel('admin');
    $fixture = schedulingFixture();
    $this->actingAs($fixture['operator']);
    $schedule = createOperationalSchedule($fixture);

    $this->get(ScheduleResource::getUrl('create', panel: 'admin'))
        ->assertOk()
        ->assertSeeText(__('scheduling::filament.schedule.sections.target'))
        ->assertSeeText(__('scheduling::filament.schedule.fields.weekdays'))
        ->assertDontSee('data.organization_id', false);

    $this->get(ScheduleResource::getUrl('view', ['record' => $schedule], panel: 'admin'))
        ->assertOk()
        ->assertSeeText('مجموعة الجدولة الحقيقية')
        ->assertSeeText('كورس الجدولة الحقيقي')
        ->assertSeeText('المعلم التشغيلي')
        ->assertSeeText(__('scheduling::filament.schedule.hub.sessions'))
        ->assertSeeText('إنشاء قالب جدول تشغيلي كامل للاختبار');

    $otherOrganization = Organization::factory()->create();
    $otherOperator = User::factory()
        ->inOrganization((string) $otherOrganization->id)
        ->create();
    $this->actingAs($otherOperator)
        ->get(ScheduleResource::getUrl('view', ['record' => $schedule], panel: 'admin'))
        ->assertNotFound();
});
