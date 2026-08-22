<?php

declare(strict_types=1);

namespace Modules\Recordings\Application\Concerns;

use Modules\Recordings\Domain\Enums\RecordingStatus;
use Modules\Recordings\Domain\Models\Recording;
use Shared\Support\BusinessRuleViolation;

/**
 * منطق الانتقال بين حالات التسجيل مشترك بين كل إجراءات الكتابة.
 *
 * القاعدة: أي انتقال يمر إجباريًا عبر canTransitionTo — لا كتابة مباشرة للحالة.
 */
trait TransitionsRecordingStatus
{
    /**
     * يُستدعى داخل معاملة قاعدة البيانات فقط.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function applyTransition(
        Recording $recording,
        RecordingStatus $to,
        array $attributes = [],
    ): void {
        $from = $recording->status;

        if (! $from->canTransitionTo($to)) {
            throw BusinessRuleViolation::make(
                'recordings.invalid_status_transition',
                'recordings::errors.invalid_transition',
                ['from' => $from->label(), 'to' => $to->label()],
            );
        }

        $recording->forceFill([...$attributes, 'status' => $to])->save();
    }
}
