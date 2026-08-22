<?php

declare(strict_types=1);

namespace Modules\Audit\Tests\Unit;

use Illuminate\Support\Facades\Event;
use Modules\Audit\Application\Actions\RecordAuditEntryAction;
use Modules\Audit\Domain\Enums\AuditActorType;
use Modules\Audit\Domain\Events\AuditEntryRecorded;
use Modules\Audit\Domain\Models\AuditLog;
use Modules\Audit\Tests\Support\RefreshAuditDatabase;
use Shared\Support\BusinessRuleViolation;
use Tests\TestCase;

/**
 * قواعد عمل تسجيل قيود التدقيق.
 */
final class RecordAuditEntryActionTest extends TestCase
{
    use RefreshAuditDatabase;

    public function test_records_entry_and_dispatches_domain_event_after_success(): void
    {
        Event::fake([AuditEntryRecorded::class]);

        $action = app(RecordAuditEntryAction::class);

        $entry = $action->execute(
            organizationId: '01ORGANIZATION000000000000',
            actorId: '01ACTOR0000000000000000000',
            actorType: AuditActorType::User,
            action: 'Session.Completed',
            auditableType: 'Modules\Sessions\Domain\Models\Session',
            auditableId: '01SESSION00000000000000000',
            oldValues: ['status' => 'awaiting_review'],
            newValues: ['status' => 'completed'],
            reason: 'اعتماد الحصة بعد مراجعة التقرير',
        );

        self::assertTrue($entry->exists);
        self::assertSame('session.completed', $entry->action);
        self::assertInstanceOf(AuditActorType::class, $entry->actor_type);
        self::assertSame('user', $entry->actor_type->value);
        self::assertSame(['status' => 'awaiting_review'], $entry->old_values);
        self::assertSame(['status' => 'completed'], $entry->new_values);

        Event::assertDispatched(AuditEntryRecorded::class, function (AuditEntryRecorded $event) use ($entry): bool {
            return $event->auditLogId === (string) $entry->getKey()
                && $event->name() === 'audit.entry_recorded'
                && $event->module() === 'audit'
                && $event->payload()['action'] === 'session.completed'
                && is_string($event->payload()['auditable_id']);
        });
    }

    public function test_rejects_sensitive_action_without_written_reason(): void
    {
        Event::fake([AuditEntryRecorded::class]);

        try {
            app(RecordAuditEntryAction::class)->execute(
                organizationId: null,
                actorId: '01ACTOR0000000000000000000',
                actorType: AuditActorType::User,
                action: 'payroll.entry_created',
                auditableType: 'payroll_entry',
            );
            self::fail('Expected BusinessRuleViolation was not thrown.');
        } catch (BusinessRuleViolation $violation) {
            self::assertSame('audit.reason_required', $violation->rule);
        }

        self::assertSame(0, AuditLog::query()->count());
    }

    public function test_accepts_sensitive_action_when_reason_is_written(): void
    {
        Event::fake([AuditEntryRecorded::class]);

        $entry = app(RecordAuditEntryAction::class)->execute(
            organizationId: null,
            actorId: null,
            actorType: AuditActorType::System,
            action: 'PAYROLL.ENTRY_CREATED',
            auditableType: 'payroll_entry',
            newValues: ['amount_minor' => 150000],
            reason: 'قيدة اعتماد حصة مكتملة',
        );

        self::assertTrue($entry->exists);
        self::assertSame('system', $entry->actor_type?->value);

        Event::assertDispatchedTimes(AuditEntryRecorded::class, 1);
    }

    public function test_reads_sensitive_patterns_from_config_not_from_code(): void
    {
        config(['audit.reason_required_actions' => ['custom.policy.*']]);

        $action = app(RecordAuditEntryAction::class);

        $free = $action->execute(
            organizationId: null,
            actorId: null,
            actorType: AuditActorType::System,
            action: 'payroll.entry_created',
            auditableType: 'payroll_entry',
        );
        self::assertTrue($free->exists);

        $this->expectException(BusinessRuleViolation::class);
        $action->execute(
            organizationId: null,
            actorId: null,
            actorType: AuditActorType::System,
            action: 'custom.policy.updated',
            auditableType: 'anything',
        );
    }

    public function test_rejects_empty_action(): void
    {
        try {
            app(RecordAuditEntryAction::class)->execute(
                organizationId: null,
                actorId: null,
                actorType: AuditActorType::System,
                action: '   ',
                auditableType: 'anything',
            );
            self::fail('Expected BusinessRuleViolation was not thrown.');
        } catch (BusinessRuleViolation $violation) {
            self::assertSame('audit.action_required', $violation->rule);
        }

        self::assertSame(0, AuditLog::query()->count());
    }
}
