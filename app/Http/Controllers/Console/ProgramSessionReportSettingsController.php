<?php

declare(strict_types=1);

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Audit\Domain\Contracts\AuditQueryService;
use Modules\Identity\Domain\Contracts\UserAccountDirectory;
use Modules\Reporting\Domain\Contracts\ProgramDigestRecipientSettings;
use Modules\Reporting\Domain\Models\ProgramDigestRecipientSetting;
use Modules\Reporting\Presentation\Http\Requests\SaveProgramDigestRecipientSettingRequest;

/**
 * شاشة إعداد مستلم التقرير الشهري المجمَّع — من يستلم بيانات الطلاب فعليًا،
 * لذلك تعرض آخر من غيّره ومتى (من audit_log) بلا حاجة لموديول جديد.
 */
final class ProgramSessionReportSettingsController extends Controller
{
    private const AUDIT_ACTION = 'reporting.settings.program_digest_recipient';

    public function __construct(
        private readonly ProgramDigestRecipientSettings $settings,
        private readonly UserAccountDirectory $accounts,
        private readonly AuditQueryService $audit,
    ) {}

    public function edit(Request $request): Response
    {
        $organizationId = (string) $request->user()?->getAttribute('organization_id');
        $current = $this->settings->current($organizationId);

        $accounts = $this->accounts->search($organizationId, '', 200);

        $lastChange = $this->audit->paginateForOrganization($organizationId, [
            'action' => self::AUDIT_ACTION,
            'auditable_type' => ProgramDigestRecipientSetting::class,
        ], perPage: 1, page: 1)->items()[0] ?? null;

        $lastChangedBy = null;
        if ($lastChange !== null) {
            $actor = $lastChange->actorId === null ? null : $this->accounts->find($organizationId, $lastChange->actorId);
            $lastChangedBy = [
                'name' => $actor->name ?? __('console_reports.settings.unknown_actor'),
                'at' => $lastChange->createdAt,
                'reason' => $lastChange->reason,
            ];
        }

        return Inertia::render('Console/Reports/Settings', [
            'current' => $current === null ? null : [
                'recipientType' => $current->recipientType,
                'recipientUserId' => $current->recipientUserId,
                'customEmail' => $current->customEmail,
                'version' => $current->version,
            ],
            'accounts' => array_map(static fn ($account): array => [
                'id' => $account->id,
                'name' => $account->name,
                'email' => $account->email,
            ], array_values(array_filter($accounts, static fn ($account): bool => $account->email !== null))),
            'lastChangedBy' => $lastChangedBy,
            'saveUrl' => route('console.reports.session-reports.settings.update'),
            'backUrl' => route('console.reports.session-reports.index'),
        ]);
    }

    public function update(SaveProgramDigestRecipientSettingRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $organizationId = (string) data_get($request->user(), 'organization_id');

        $this->settings->saveGlobal(
            $organizationId,
            (string) $data['recipient_type'],
            $data['recipient_user_id'] ?? null,
            $data['custom_email'] ?? null,
            (string) $request->user()?->getAuthIdentifier(),
            (string) $data['reason'],
            $data['version'] ?? null,
        );

        return to_route('console.reports.session-reports.settings.edit')
            ->with('success', __('console_reports.settings.saved'));
    }
}
