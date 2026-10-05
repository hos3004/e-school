<?php

declare(strict_types=1);

namespace App\Http\Controllers\Learning;

use App\Http\Controllers\Console\Support\ConsoleContext;
use App\Http\Controllers\Portal\SessionPostponementRequestController;
use App\Http\Controllers\Portal\StudentSessionApologyController;
use App\Http\Controllers\Portal\Support\PortalData;
use App\Http\Controllers\Portal\TeacherPostponementResponseController;
use App\Http\Requests\Learning\SessionChangeRequest;
use App\Http\Requests\Portal\RejectPostponementRequest;
use App\Http\Requests\Portal\RequestSessionPostponementRequest;
use App\Http\Requests\Portal\RespondToPostponementRequest;
use App\Http\Requests\Portal\SubmitStudentSessionApologyRequest;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Presentation adapter: existing request policies and scheduling actions remain authoritative. */
final class LearningSessionRequestsController
{
    public function index(Request $request, PortalData $data): Response
    {
        $org = (string) $request->user()?->getAttribute('organization_id');
        $staff = $data->staffProfileId((string) $request->user()?->getAuthIdentifier(), $org);
        abort_if($staff === null, 403);
        $requests = $data->teacherPostponements($staff, 'ar', $org);
        foreach ($requests as &$item) {
            foreach (['approveUrl' => 'approve', 'proposeAlternativeUrl' => 'propose', 'rejectUrl' => 'reject'] as $key => $action) {
                $item[$key] = empty($item[$key]) ? null : route('learning.teacher.postponements.'.$action, ['postponement' => $item['id']]);
            }
        }
        unset($item);

        return Inertia::render('Learning/Postponements', ['requests' => $requests, 'timezone' => app(ConsoleContext::class)->forRequest($request)['timezone']]);
    }

    public function postpone(SessionChangeRequest $request, string $kind, string $session, SessionPostponementRequestController $controller): RedirectResponse
    {
        abort_unless(in_array($kind, ['student', 'teacher'], true), 404);
        $form = $this->adapt($request, RequestSessionPostponementRequest::class, ['proposed_start' => $this->proposed($request), 'reason' => $this->reason($request)]);

        return $kind === 'student' ? $controller->student($form, $session) : $controller->teacher($form, $session);
    }

    public function apologize(SessionChangeRequest $request, string $session, StudentSessionApologyController $controller): RedirectResponse
    {
        return $controller($this->adapt($request, SubmitStudentSessionApologyRequest::class, ['reason' => $this->reason($request)]), $session);
    }

    public function propose(SessionChangeRequest $request, string $postponement, TeacherPostponementResponseController $controller): RedirectResponse
    {
        return $controller->propose($this->adapt($request, RespondToPostponementRequest::class, ['proposed_start_at' => $this->proposed($request), 'reason' => $this->reason($request)]), $postponement);
    }

    public function reject(SessionChangeRequest $request, string $postponement, TeacherPostponementResponseController $controller): RedirectResponse
    {
        return $controller->reject($this->adapt($request, RejectPostponementRequest::class, ['reason' => $this->reason($request)]), $postponement);
    }

    private function proposed(SessionChangeRequest $request): string
    {
        $zone = (string) app(ConsoleContext::class)->forRequest($request)['timezone'];

        return CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $request->string('proposed_local')->toString(), $zone)->utc()->toIso8601String();
    }

    private function reason(SessionChangeRequest $request): string
    {
        $data = $request->validated();

        return (string) __('learning.requests.categories.'.$data['category']).(empty($data['note']) ? '' : ': '.$data['note']);
    }

    /** @template T of FormRequest
     * @param class-string<T> $class
     * @param array<string, mixed> $payload
     * @return T
     */
    private function adapt(Request $request, string $class, array $payload): FormRequest
    {
        $form = $class::createFrom($request);
        $form->replace($payload);
        $form->setContainer(app())->setRedirector(app('redirect'));
        $form->validateResolved();

        return $form;
    }
}
