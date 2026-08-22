<?php

declare(strict_types=1);

namespace Modules\Recordings\Application\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Recordings\Domain\Models\RecordingView;

/**
 * سياسة سجل المشاهدات — قراءة تدقيق فقط.
 */
final class RecordingViewPolicy
{
    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('recordings.recording_view.view_any');
    }

    public function view(Authenticatable&Authorizable $user, RecordingView $view): bool
    {
        return $user->can('recordings.recording_view.view')
            && $view->recording !== null
            && $view->recording->organization_id === data_get($user, 'organization_id');
    }
}
