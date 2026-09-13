<?php

declare(strict_types=1);

namespace App\Http\Requests\Console;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;
use Modules\Staff\Domain\Enums\ContractBasis;
use Modules\Staff\Domain\Enums\EmploymentType;
use Shared\Support\Locales;

final class PeopleStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can($this->route('kind') === 'students' ? 'student.create' : 'staff.contract.update') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $new = $this->input('account_mode') === 'new';
        $student = $this->route('kind') === 'students';
        $required = $new ? 'required' : 'nullable';
        $rules = [
            'account_mode' => ['required', Rule::in(['new', 'existing'])],
            'existing_user_id' => [$new ? 'nullable' : 'required', 'ulid'],
            'full_name' => [$required, 'string', 'max:255'],
            'username' => [$required, 'string', 'min:'.config('admission.username.min_length'), 'max:'.config('admission.username.max_length')],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'password' => [$required, Password::defaults(), 'confirmed'],
            'locale' => ['required', Rule::in(Locales::supported())],
            'timezone' => ['required', 'timezone:all'],
            // الإنشاء الإداري السريع: الاسم واسم المستخدم وكلمة المرور تكفي،
            // وما يخص الشخص نفسه يُكمله صاحب الحساب عند أول دخول.
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', Rule::in(['male', 'female'])],
            'country_id' => ['nullable', 'ulid', 'required_with:region_id'],
            'region_id' => ['nullable', 'ulid', 'required_with:country_id'],
        ];
        // The action validates confirmation too; keep it in the validated payload without ever flashing it.
        $rules['password_confirmation'] = [$required, 'string', 'same:password'];

        // حقول المعلم اختيارية، ولا تُقبل أصلًا ممن لا يملك إدارة الجداول.
        $teaching = $this->user()?->can('schedule.manage') ? 'nullable' : 'prohibited';
        // والتسكين في مجموعة يحتاج صلاحيتي القيد وإدارة المجموعات معًا.
        $actor = $this->user();
        $placing = $actor !== null && $actor->can('enrollment.create') && $actor->can('group.manage')
            ? 'nullable' : 'prohibited';

        return $student ? [...$rules,
            'preferred_program_id' => ['required', 'ulid'],
            'preferred_course_id' => ['required', 'ulid'],
            'nationality' => ['nullable', 'string', 'size:2'],
            'city' => ['nullable', 'string', 'max:120'],
            'preferred_language' => ['nullable', Rule::in(Locales::supported())],
            'notes' => ['nullable', 'string', 'max:5000'],
            'teaching_staff_profile_id' => [$teaching, 'string', 'size:26', 'required_with:teaching_weekday,teaching_start_time,teaching_starts_on'],
            'teaching_duration_minutes' => [$teaching, 'integer', Rule::in((array) config('scheduling.individual_session_durations')), 'required_with:teaching_staff_profile_id'],
            'teaching_weekday' => [$teaching, 'integer', 'between:0,6', 'required_with:teaching_start_time'],
            'teaching_start_time' => [$teaching, 'date_format:H:i', 'required_with:teaching_weekday'],
            'teaching_starts_on' => [$teaching, 'date_format:Y-m-d', 'required_with:teaching_start_time'],
            'placement_mode' => [$placing, Rule::in(['existing', 'new'])],
            'placement_group_id' => [$placing, 'ulid', 'required_if:placement_mode,existing'],
            'placement_group_code' => [
                $placing, 'string', 'max:32', 'regex:/^[A-Za-z0-9-]+$/',
                Rule::unique('groups', 'code'), 'required_if:placement_mode,new',
            ],
            'placement_group_name' => [$placing, 'string', 'max:120', 'required_if:placement_mode,new'],
            'placement_group_capacity' => [
                $placing, 'integer',
                'min:'.config('groups.capacity.minimum'), 'max:'.config('groups.capacity.maximum'),
            ],
            'placement_group_starts_on' => [$placing, 'date_format:Y-m-d'],
        ] : [...$rules,
            'staff_code' => ['required', 'string', 'max:32'],
            'employment_type' => ['required', Rule::enum(EmploymentType::class)],
            'hired_at' => ['required', 'date', ...($this->filled('date_of_birth') ? ['after:date_of_birth'] : [])],
            'bio' => ['nullable', 'string', 'max:5000'],
            'specializations' => ['nullable', 'array'],
            'specializations.*' => ['string', 'max:120'],
            'contract_basis' => ['required', Rule::enum(ContractBasis::class)],
            'contract_effective_from' => ['required', 'date'],
            'contract_effective_to' => ['nullable', 'date', 'after:contract_effective_from'],
            'currency' => ['required', Rule::in((array) config('staff.currency.supported'))],
            'base_amount_major' => ['nullable', 'decimal:0,2', 'min:0'],
            'default_rate_major' => ['nullable', 'decimal:0,2', 'gt:0'],
            'monthly_target_sessions' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'target_admin_tasks' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'target_training_sessions' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'course_ids' => ['nullable', 'array'],
            'course_ids.*' => ['ulid', 'distinct'],
            'qualification_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * المعلم الفردي والمجموعة تسكينان متنافيان لكورس واحد؛ لا يُقبلان معًا.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->filled('teaching_staff_profile_id') && $this->filled('placement_mode')) {
                $validator->errors()->add('placement_mode', __('console_people.placement.teacher_or_group'));
            }
        }];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'teaching_staff_profile_id' => __('console_people.fields.teaching_staff_profile_id'),
            'teaching_duration_minutes' => __('console_people.fields.teaching_duration_minutes'),
            'teaching_weekday' => __('console_people.fields.teaching_weekday'),
            'teaching_start_time' => __('console_people.fields.teaching_start_time'),
            'teaching_starts_on' => __('console_people.fields.teaching_starts_on'),
            'placement_group_id' => __('console_people.fields.placement_group_id'),
            'placement_group_code' => __('console_people.fields.placement_group_code'),
            'placement_group_name' => __('console_people.fields.placement_group_name'),
            'placement_group_capacity' => __('console_people.fields.placement_group_capacity'),
            'placement_group_starts_on' => __('console_people.fields.placement_group_starts_on'),
        ];
    }
}
