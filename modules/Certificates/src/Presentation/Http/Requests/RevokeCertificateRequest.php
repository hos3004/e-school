<?php

declare(strict_types=1);

namespace Modules\Certificates\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * طلب سحب شهادة — السبب إلزامي موثق.
 */
final class RevokeCertificateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('certificates.certificate.revoke');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'reason' => __('certificates::fields.reason'),
        ];
    }
}
