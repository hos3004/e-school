<?php

declare(strict_types=1);

namespace App\Http\Requests\Console;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Notifications\Domain\Enums\Channel;

final class SaveConsoleSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $permissions = match ($this->route('operation')) {
            'accounts' => ['organizations.manage_settings'],
            'notifications' => ['settings.manage'],
            'calendar-create' => ['academic_calendars.view_any', 'academic_calendars.create'],
            'calendar-activate' => ['academic_calendars.view_any', 'academic_calendars.activate'],
            'calendar-close' => ['academic_calendars.view_any', 'academic_calendars.close'],
            'holiday-create' => ['holidays.view_any', 'holidays.create'],
            'holiday-remove' => ['holidays.view_any', 'holidays.delete'],
            default => [],
        };

        return $permissions !== [] && collect($permissions)->every(fn (string $permission): bool => (bool) $this->user()?->can($permission));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return match ($this->route('operation')) {
            'accounts' => [
                'username_prefix' => ['nullable', 'string', 'max:'.config('admission.username.max_length'), 'regex:/^[a-z][a-z0-9]*$/'],
                'version' => ['required', 'string', 'size:64'],
            ],
            'notifications' => [
                'category' => ['required', Rule::in(array_keys((array) config('notifications.categories')))],
                'channels' => ['required', 'array', 'min:1'],
                'channels.*' => ['required', 'string', 'distinct', Rule::in(Channel::values())],
                'is_critical' => ['required', 'boolean'],
                'respects_quiet_hours' => ['required', 'boolean'],
                'version' => ['required', 'string', 'size:64'],
            ],
            'calendar-create' => [
                'name' => ['required', 'string', 'max:200'], 'starts_on' => ['required', 'date_format:Y-m-d'],
                'ends_on' => ['required', 'date_format:Y-m-d', 'after:starts_on'],
            ],
            'holiday-create' => [
                'name' => ['required', 'string', 'max:200'], 'starts_on' => ['required', 'date_format:Y-m-d'],
                'ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
                'academic_calendar_id' => ['nullable', 'ulid'], 'blocks_scheduling' => ['required', 'boolean'],
            ],
            default => ['id' => ['required', 'ulid'], 'version' => ['required', 'string', 'size:64']],
        };
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'username_prefix' => __('console_settings.username_prefix'), 'category' => __('console_settings.category'),
            'channels' => __('console_settings.channels'), 'name' => __('console_settings.name'),
            'starts_on' => __('console_settings.starts_on'), 'ends_on' => __('console_settings.ends_on'),
            'academic_calendar_id' => __('console_settings.calendar'),
        ];
    }
}
