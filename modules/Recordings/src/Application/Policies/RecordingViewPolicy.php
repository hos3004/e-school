<?php

declare(strict_types=1);

namespace Modules\Recordings\Application\Policies;

use Modules\Recordings\Domain\Models\RecordingView;

/**
 * سياسة سجل المشاهدات — قراءة تدقيق فقط.
 */
final class RecordingViewPolicy
{
    public function viewAny($user): bool
    {
        return $user->can('recordings.recording_view.view_any');
    }

    public function view($user, RecordingView $view): bool
    {
        return $user->can('recordings.recording_view.view')
            && $view->recording !== null
            && $view->recording->organization_id === $user->organization_id;
    }
}
