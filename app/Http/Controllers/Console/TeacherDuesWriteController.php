<?php

declare(strict_types=1);

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Http\Requests\Console\TeacherDuesDecisionRequest;
use App\Http\Requests\Console\TeacherDuesProposeRequest;
use App\Http\Requests\Console\TeacherDuesSessionRequest;
use App\Services\Console\SessionDecisionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Modules\Payroll\Domain\Contracts\TeacherDuesOperations;
use Modules\Payroll\Domain\ValueObjects\TeacherDuesMoney;
use Modules\Sessions\Domain\Models\Session;
use Shared\Support\BusinessRuleViolation;

final class TeacherDuesWriteController extends Controller
{
    public function propose(TeacherDuesProposeRequest $request, string $period, TeacherDuesOperations $operations): RedirectResponse
    {
        abort_unless((bool) config('features.payroll'), 404);
        $result = $operations->propose($request->user(), $period, $request->validated());

        return redirect()->route('console.teacher-dues.index', ['period' => $result['periodId'], 'teacher' => $result['staffProfileId']])
            ->with('success', __('console_dues.'.($result['approved'] ? 'proposed_approved' : 'proposed')));
    }

    public function decide(TeacherDuesDecisionRequest $request, string $adjustment, TeacherDuesOperations $operations): RedirectResponse
    {
        abort_unless((bool) config('features.payroll'), 404);
        $approve = $request->route('decision') === 'approve';
        $result = $operations->decide($request->user(), $adjustment, $approve, (string) $request->validated('reason'));

        return redirect()->route('console.teacher-dues.index', ['period' => $result['periodId'], 'teacher' => $result['staffProfileId']])
            ->with('success', __('console_dues.'.($approve ? 'approved_success' : 'rejected')));
    }

    /**
     * قرار على حصة دون مغادرة ملف حسابات المعلم.
     *
     * القرار نفسه الذي تنفّذه شاشة الاعتماد وبنفس الخدمة، لأن الرقم الذي
     * يراه المدير هنا هو ناتج هذه القرارات: مطالبته بفتح شاشة أخرى ليغيّره
     * هو سبب بقاء حصص بلا قرار أسابيع.
     */
    public function decideSession(
        TeacherDuesSessionRequest $request,
        string $session,
        SessionDecisionService $decisions,
    ): RedirectResponse {
        abort_unless((bool) config('features.payroll'), 404);
        $organizationId = (string) $request->user()?->getAttribute('organization_id');
        $actorId = (string) $request->user()?->getAuthIdentifier();
        $data = $request->validated();

        /** @var Session $record */
        $record = Session::query()->forOrganization($organizationId)->findOrFail($session);

        /*
         * الحالة المتوقعة تأتي من الشاشة التي رآها المستخدم. لو تغيّرت الحصة
         * بين العرض والقرار — اعتمدها زميل أو أقفلها الأمر المجدول — يُرفض
         * القرار بدل أن يُبنى على شاشة قديمة.
         */
        if ($record->status->value !== $data['expected_status']) {
            throw ValidationException::withMessages(['decision' => __('console_dues.session_stale')]);
        }

        $amount = isset($data['amount']) && trim((string) $data['amount']) !== ''
            ? TeacherDuesMoney::fromMajor((string) $data['amount'], (string) config('payroll.currency'))->minorUnits
            : null;

        try {
            $decisions->decide($record, (string) $data['decision'], $actorId, (string) $data['reason'], $amount);
        } catch (BusinessRuleViolation $violation) {
            throw ValidationException::withMessages(['decision' => $violation->getMessage()]);
        }

        return back()->with('success', __('console_dues.session_recorded'));
    }
}
