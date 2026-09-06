<?php

declare(strict_types=1);

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Http\Requests\Console\TeacherDuesDecisionRequest;
use App\Http\Requests\Console\TeacherDuesProposeRequest;
use Illuminate\Http\RedirectResponse;
use Modules\Payroll\Domain\Contracts\TeacherDuesOperations;

final class TeacherDuesWriteController extends Controller
{
    public function propose(TeacherDuesProposeRequest $request, string $period, TeacherDuesOperations $operations): RedirectResponse
    {
        abort_unless((bool) config('features.payroll'), 404);
        $result = $operations->propose($request->user(), $period, $request->validated());

        return redirect()->route('console.teacher-dues.index', ['period' => $result['periodId'], 'teacher' => $result['staffProfileId']])
            ->with('success', __('console_dues.proposed'));
    }

    public function decide(TeacherDuesDecisionRequest $request, string $adjustment, TeacherDuesOperations $operations): RedirectResponse
    {
        abort_unless((bool) config('features.payroll'), 404);
        $approve = $request->route('decision') === 'approve';
        $result = $operations->decide($request->user(), $adjustment, $approve, (string) $request->validated('reason'));

        return redirect()->route('console.teacher-dues.index', ['period' => $result['periodId'], 'teacher' => $result['staffProfileId']])
            ->with('success', __('console_dues.'.($approve ? 'approved_success' : 'rejected')));
    }
}
