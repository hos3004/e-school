<?php

declare(strict_types=1);

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Http\Requests\Console\TeacherFinancialVisibilityRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Staff\Domain\Models\StaffProfile;

final class TeacherFinancialVisibilityController extends Controller
{
    public function __invoke(TeacherFinancialVisibilityRequest $request, string $profile, AuditRecorder $audit): RedirectResponse
    {
        DB::transaction(function () use ($request, $profile, $audit): void {
            $record = StaffProfile::query()->forOrganization((string) $request->user()->getAttribute('organization_id'))->whereKey($profile)->lockForUpdate()->firstOrFail();
            Gate::authorize('update', $record);
            $before = $record->financials_visible;
            $after = $request->boolean('financials_visible');
            if ($before === $after) {
                return;
            }
            $record->financials_visible = $after;
            $record->save();
            $audit->record(organizationId: $record->organization_id, actorId: (string) $request->user()->getAuthIdentifier(), actorType: 'user', action: 'staff.financial_visibility_changed', auditableType: 'staff_profiles', auditableId: $record->id, oldValues: ['financials_visible' => $before], newValues: ['financials_visible' => $after], reason: (string) $request->validated('reason'));
        });

        return back()->with('success', __('teacher_visibility.saved'));
    }
}
