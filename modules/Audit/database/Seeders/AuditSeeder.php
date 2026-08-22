<?php

declare(strict_types=1);

namespace Modules\Audit\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Modules\Audit\Application\Actions\RecordAuditEntryAction;
use Modules\Audit\Domain\Enums\AuditActorType;
use Modules\Audit\Domain\Models\AuditLog;

/**
 * بيانات تجريبية معقولة لدفتر التدقيق.
 *
 * تمر عبر RecordAuditEntryAction نفسه — لا إدخال مباشر — حتى تكون
 * القيود التجريبية مطابقة تمامًا لما ينتجه الإنتاج (حراس + حدث).
 */
final class AuditSeeder extends Seeder
{
    public function run(): void
    {
        if (AuditLog::query()->exists()) {
            return;
        }

        $action = app(RecordAuditEntryAction::class);

        $action->execute(
            organizationId: null,
            actorId: null,
            actorType: AuditActorType::System,
            action: 'system.bootstrapped',
            auditableType: 'platform',
            newValues: ['seeded_at' => now()->utc()->toIso8601String()],
        );

        $actorId = (string) Str::ulid();
        $sessionId = (string) Str::ulid();

        $action->execute(
            organizationId: null,
            actorId: $actorId,
            actorType: AuditActorType::User,
            action: 'presence.updated',
            auditableType: 'Modules\\Sessions\\Domain\\Models\\Session',
            auditableId: $sessionId,
            oldValues: ['status' => 'awaiting_review'],
            newValues: ['status' => 'completed'],
            reason: __('audit::messages.seeder_presence_reason'),
        );

        $action->execute(
            organizationId: null,
            actorId: $actorId,
            actorType: AuditActorType::User,
            action: 'permission_changed',
            auditableType: 'user',
            auditableId: $actorId,
            oldValues: ['can' => ['audit.view']],
            newValues: ['can' => ['audit.view', 'audit.view_any']],
            reason: __('audit::messages.seeder_permission_reason'),
        );
    }
}
