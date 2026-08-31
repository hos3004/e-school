<?php

declare(strict_types=1);

namespace Modules\Notifications\Presentation\Http\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\Notifications\Domain\Models\NotificationOutbox;

final readonly class NotificationDeepLinkResolver
{
    public function resolve(Request $request, NotificationOutbox $outbox): ?string
    {
        $configured = data_get($outbox->payload, 'target_url');
        if (is_string($configured)
            && str_starts_with($configured, '/')
            && !str_starts_with($configured, '//')) {
            return $configured;
        }

        $sessionId = data_get($outbox->payload, 'session_id');
        if (is_string($sessionId) && Str::isUlid($sessionId)) {
            if ($request->user()?->can('attendance.record') === true
                || $request->user()?->can('session_report.create') === true) {
                return "/teacher/sessions/{$sessionId}";
            }

            if ($request->user()?->can('session.view') === true) {
                return "/student/sessions/{$sessionId}";
            }
        }

        if (str_starts_with($outbox->event_name, 'assignment.')
            || $outbox->event_name === 'submission.graded') {
            return $request->user()?->can('assignment.submit') === true
                ? '/student/assignments'
                : null;
        }

        if (str_starts_with($outbox->event_name, 'registration.')
            || str_starts_with($outbox->event_name, 'discipline.')) {
            return $request->user()?->can('enrollment.view') === true
                ? '/student/programs'
                : null;
        }

        return null;
    }
}
