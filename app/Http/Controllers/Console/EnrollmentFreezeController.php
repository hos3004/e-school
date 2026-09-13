<?php

declare(strict_types=1);

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Http\Requests\Console\PersonLifecycleRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Modules\Enrollments\Application\Actions\FreezeEnrollmentAction;
use Modules\Enrollments\Domain\Models\Enrollment;

/**
 * تجميد قيد الطالب في برنامج واحد من صفحة ملفه.
 *
 * التجميد يخص البرنامج الذي صدر فيه القرار وحده؛ بقية برامج الطالب لا تتأثر،
 * والحساب وبياناته وسجله تبقى كما هي — منع وصول للكورس لا حذف.
 *
 * النوع من الواجهة يدوي دائمًا؛ التجميد الآلي مسار النظام التأديبي وحده.
 * التدقيق يكتبه انتقال الحالة داخل الـaction، فلا يُكرَّر هنا.
 */
final class EnrollmentFreezeController extends Controller
{
    public function __invoke(
        PersonLifecycleRequest $request,
        string $enrollment,
        FreezeEnrollmentAction $action,
    ): RedirectResponse {
        $record = Enrollment::query()
            ->where('organization_id', $request->organizationId())
            ->whereKey($enrollment)
            ->firstOrFail();
        Gate::authorize('freeze', $record);

        $action->execute($record, $request->reason(), 'manual', $request->actorId());

        return back()->with('success', __('console_people.lifecycle.frozen'));
    }
}
