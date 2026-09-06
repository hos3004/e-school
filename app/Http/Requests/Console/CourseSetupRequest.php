<?php

declare(strict_types=1);

namespace App\Http\Requests\Console;

use App\Http\Controllers\Console\Support\ConsoleMoney;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Lang;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Modules\Academics\Domain\Enums\ProgramType;
use Modules\Academics\Domain\Enums\SessionMode;
use Modules\Academics\Domain\Enums\TargetGender;

final class CourseSetupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($this->user()?->can($this->route('kind') === 'items' ? 'course.manage' : 'program.manage') ?? false)
            && (!$this->filled('first_group_name') || $this->user()->can('group.manage'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $kind = (string) $this->route('kind');
        $id = $this->route('id');
        $table = match ($kind) {
            'programs' => 'programs', 'levels' => 'levels', default => 'courses'
        };
        $unique = Rule::unique($table, 'code')->ignore(is_string($id) ? $id : null);
        if ($kind === 'levels') {
            $unique->where('program_id', (string) $this->input('program_id'));
        }
        $rules = [
            'organization_id' => ['prohibited'],
            'code' => ['required', 'string', 'max:32', 'regex:/^[A-Za-z0-9_-]+$/', $unique],
            'name' => ['required', 'array:ar,en,fr'],
            'name.ar' => ['required', 'string', 'max:255'],
            'name.en' => ['nullable', 'string', 'max:255'],
            'name.fr' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'array:ar,en,fr'],
            'description.*' => ['nullable', 'string', 'max:2000'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
        if ($kind === 'levels') {
            unset($rules['description'], $rules['description.*']);

            return [...$rules,
                'program_id' => ['required', 'string', 'size:26', Rule::exists('programs', 'id')->where('organization_id', (string) $this->user()?->organization_id)->whereNull('deleted_at')],
            ];
        }
        $rules = [...$rules,
            'is_active' => ['required', 'boolean'],
            'target_gender' => ['nullable', Rule::enum(TargetGender::class)],
            'age_from' => ['nullable', 'integer', 'min:'.config('academics.age.minimum'), 'max:'.config('academics.age.maximum')],
            'age_to' => ['nullable', 'integer', 'min:'.config('academics.age.minimum'), 'max:'.config('academics.age.maximum'), 'gte:age_from'],
        ];
        if ($kind === 'items') {
            unset($rules['sort_order']);

            return [...$rules,
                'level_id' => ['required', 'string', 'size:26'],
                'first_group_name' => ['nullable', 'string', 'max:120', Rule::prohibitedIf(is_string($id) || $this->input('session_mode') === 'individual')],
                'first_group_capacity' => ['nullable', 'integer', 'min:'.config('groups.capacity.minimum'), 'max:'.config('groups.capacity.maximum')],
                'first_group_starts_on' => ['nullable', 'date'],
                'first_group_ends_on' => ['nullable', 'date', 'after_or_equal:first_group_starts_on'],
                'session_mode' => ['required', Rule::enum(SessionMode::class)],
                'total_sessions' => ['nullable', 'integer', 'min:1'],
                'default_duration_minutes' => ['nullable', 'integer', 'min:'.config('academics.session_minutes.course_minimum'), 'max:'.config('academics.session_minutes.maximum')],
                'sessions_per_week' => ['nullable', 'integer', 'min:'.config('academics.sessions_per_week.minimum'), 'max:'.config('academics.sessions_per_week.maximum')],
            ];
        }

        return [...$rules,
            'program_type' => ['required', Rule::enum(ProgramType::class)],
            'start_date' => ['nullable', 'date', 'required_if:program_type,fixed_duration'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date', 'required_if:program_type,fixed_duration', 'prohibited_if:program_type,ongoing'],
            'duration_weeks' => ['nullable', 'integer', 'min:1'],
            'default_session_minutes' => ['required', 'integer', 'min:'.config('academics.session_minutes.minimum'), 'max:'.config('academics.session_minutes.maximum')],
            'default_rate' => ['prohibited'],
            'default_rate_amount' => ['nullable', 'string', 'max:100', function (string $attribute, mixed $value, Closure $fail): void {
                try {
                    ConsoleMoney::fromMajor((string) $value, (string) $this->input('currency'));
                } catch (InvalidArgumentException $exception) {
                    $fail($exception->getMessage());
                }
            }],
            'currency' => ['required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/', function (string $attribute, mixed $value, Closure $fail): void {
                try {
                    ConsoleMoney::fractionDigits((string) $value);
                } catch (InvalidArgumentException $exception) {
                    $fail($exception->getMessage());
                }
            }],
            'target_gender' => ['required', Rule::enum(TargetGender::class)],
            'language' => ['nullable', 'string', 'max:16'],
        ];
    }

    /** @return array<string, mixed> */
    public function actionData(): array
    {
        $data = $this->validated();
        if ($this->route('kind') === 'programs') {
            unset($data['default_rate']);
            if (array_key_exists('default_rate_amount', $data)) {
                $data['default_rate'] = $data['default_rate_amount'] === null
                    ? null
                    : ConsoleMoney::fromMajor((string) $data['default_rate_amount'], (string) $data['currency'])->minorUnits;
            }
            unset($data['default_rate_amount']);
        }

        $fields = match ($this->route('kind')) {
            'levels' => ['program_id', 'code', 'name', 'sort_order'],
            'programs' => [
                'code', 'name', 'description', 'sort_order', 'is_active', 'target_gender', 'age_from', 'age_to',
                'program_type', 'start_date', 'end_date', 'duration_weeks', 'default_session_minutes',
                'default_rate', 'currency', 'language',
            ],
            default => [
                'code', 'name', 'description', 'is_active', 'target_gender', 'age_from', 'age_to',
                'level_id', 'session_mode', 'total_sessions', 'default_duration_minutes', 'sessions_per_week',
            ],
        };

        return Arr::only($data, $fields);
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return collect(array_keys($this->rules()))->mapWithKeys(function (string $key): array {
            $label = 'console_courses.fields.'.str_replace('.', '_', $key);

            return [$key => Lang::has($label, app()->getLocale(), false)
                ? __($label)
                : ((array) __('validation.attributes'))[$key] ?? $key];
        })->all();
    }
}
