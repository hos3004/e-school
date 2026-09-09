<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\AccountProfile;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Modules\Identity\Domain\Models\User;
use Modules\Organization\Domain\Contracts\GeographyQueries;

final class CompleteProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->can('update', $user) && app(AccountProfile::class)->exists($user);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => mb_strtolower(trim((string) $this->input('email'))),
            'phone' => preg_replace('/[\s().-]+/u', '', strtr((string) $this->input('phone'), ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9'])),
            'city' => trim((string) $this->input('city')),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:191'],
            'email' => ['required', 'email:rfc', 'max:191', Rule::unique('users', 'email')->ignore($this->user()?->getAuthIdentifier()), function (string $attribute, mixed $value, Closure $fail): void {
                if (preg_match('/@(.*\.)?(invalid|example\.(com|org|net))$/i', (string) $value)) {
                    $fail(__('profile_completion.real_email'));
                }
            }],
            'phone' => ['required', 'string', 'regex:/^\+[1-9][0-9]{7,14}$/'],
            'country_id' => ['required', 'ulid'], 'region_id' => ['nullable', 'ulid'], 'region_name' => ['required_without:region_id', 'nullable', 'string', 'max:191'],
            'city' => ['required', 'string', 'max:191'],
            'date_of_birth' => ['required', 'date_format:Y-m-d', 'before:today'],
            'gender' => ['required', Rule::in(['male', 'female'])],
            'timezone' => ['required', 'timezone:all'],
            'confirmed' => ['accepted'],
        ];
    }

    /** @return list<Closure> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $geo = app(GeographyQueries::class);
            $country = collect($geo->countries())->firstWhere('id', $this->input('country_id'));
            if ($country === null || $country->iso2 === 'ZZ') {
                $validator->errors()->add('country_id', __('profile_completion.real_location'));

                return;
            }
            if (!$this->filled('region_id')) {
                return;
            }
            $region = collect($geo->regionsOf($country->id))->firstWhere('id', $this->input('region_id'));
            if ($region === null || str_ends_with($region->code, '0000') || $region->code === 'UNSPECIFIED') {
                $validator->errors()->add('region_id', __('profile_completion.real_location'));
            }
        }];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        $fields = ['name', 'email', 'phone', 'country_id', 'region_id', 'region_name', 'city', 'date_of_birth', 'gender', 'timezone', 'confirmed'];

        return array_combine($fields, array_map(fn (string $field): string => __('profile_completion.'.$field), $fields));
    }
}
