<?php

declare(strict_types=1);

namespace Modules\Reporting\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * طلب بناء لقطة تنظيمية.
 */
final class StoreOrganizationSnapshotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('reporting.snapshot.build');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'snapshot_date' => ['required', 'date'],
            'students_active' => ['required', 'integer', 'min:0'],
            'students_frozen' => ['required', 'integer', 'min:0'],
            'teachers_active' => ['required', 'integer', 'min:0'],
            'sessions_held' => ['required', 'integer', 'min:0'],
            'sessions_cancelled' => ['required', 'integer', 'min:0'],
            'attendance_rate_bp' => ['nullable', 'integer', 'min:0', 'max:10000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'snapshot_date' => __('reporting::fields.snapshot_date'),
            'students_active' => __('reporting::fields.students_active'),
            'students_frozen' => __('reporting::fields.students_frozen'),
            'teachers_active' => __('reporting::fields.teachers_active'),
            'sessions_held' => __('reporting::fields.sessions_held'),
            'sessions_cancelled' => __('reporting::fields.sessions_cancelled'),
            'attendance_rate_bp' => __('reporting::fields.attendance_rate'),
        ];
    }
}
