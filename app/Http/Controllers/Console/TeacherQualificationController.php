<?php

declare(strict_types=1);

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Http\Requests\Console\TeacherQualificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Modules\Staff\Application\Actions\AssignTeacherQualificationsAction;
use Modules\Staff\Application\Actions\RevokeTeacherQualificationAction;
use Modules\Staff\Domain\Models\StaffProfile;

/**
 * اعتماد الكورسات للمعلم وسحبها من صفحة ملفه في الكونسول.
 *
 * التأهيل هو ما يجعل المعلم يظهر في قوائم الإسناد، وكان يُدخَل مرة واحدة عند
 * إنشاء الملف فقط، فلا سبيل لتصحيحه بعد إضافة كورس جديد إلا من لوحة v2.
 * هذا المسار يفتح العملية نفسها داخل الكونسول دون تكرار منطقها: الاعتماد والسحب
 * يمران على Actions الموديول نفسها بما فيهما التدقيق ومنع سحب اعتماد لإسناد قائم.
 */
final class TeacherQualificationController extends Controller
{
    public function store(
        TeacherQualificationRequest $request,
        string $profile,
        AssignTeacherQualificationsAction $action,
    ): RedirectResponse {
        $record = $this->teacher($request, $profile);

        $action->execute(
            profile: $record,
            courseIds: $request->courseIds(),
            actorId: $request->actorId(),
            reason: $request->reason(),
        );

        return back()->with('success', __('console_people.qualifications.assigned'));
    }

    public function destroy(
        TeacherQualificationRequest $request,
        string $profile,
        RevokeTeacherQualificationAction $action,
    ): RedirectResponse {
        $record = $this->teacher($request, $profile);

        $action->execute(
            profile: $record,
            courseId: $request->courseId(),
            actorId: $request->actorId(),
            reason: $request->reason(),
        );

        return back()->with('success', __('console_people.qualifications.revoked'));
    }

    /** ملف معلم قائم داخل مؤسسة المستخدم — الموقوف لا يُعدَّل تأهيله. */
    private function teacher(TeacherQualificationRequest $request, string $profile): StaffProfile
    {
        /** @var StaffProfile $record */
        $record = StaffProfile::query()
            ->forOrganization($request->organizationId())
            ->whereKey($profile)
            ->firstOrFail();
        Gate::authorize('update', $record);

        return $record;
    }
}
