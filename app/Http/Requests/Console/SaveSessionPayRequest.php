<?php

declare(strict_types=1);

namespace App\Http\Requests\Console;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class SaveSessionPayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('organizations.manage_settings');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'version' => ['required', 'string', 'size:64'],
            'reason' => ['required', 'string', 'max:1000'],
            'rates' => ['required', 'array', 'min:1', 'max:100'],
            'rates.*' => ['required', 'array:name,session_type,duration_minutes,price'],
            'rates.*.name' => ['required', 'string', 'max:100'],
            'rates.*.session_type' => ['required', Rule::in(['individual', 'group'])],
            'rates.*.duration_minutes' => ['required', 'integer', 'between:'.config('session_pay.min_duration').','.config('session_pay.max_duration')],
            'rates.*.price' => ['required', 'numeric', 'min:0', 'max:'.((int) config('session_pay.max_amount_minor') / 100), 'regex:/^\d+(\.\d{1,2})?$/'],
        ];
    }

    /** @return list<\Closure> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $keys = [];
            foreach ((array) $this->input('rates', []) as $index => $row) {
                if (!is_array($row)) {
                    continue;
                }
                $key = ($row['session_type'] ?? '').':'.($row['duration_minutes'] ?? '');
                if (in_array($key, $keys, true)) {
                    $validator->errors()->add('rates.'.$index.'.duration_minutes', __('session_pay.duplicate'));
                }
                $keys[] = $key;
            }
        }];
    }
}
