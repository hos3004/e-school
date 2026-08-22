<?php

declare(strict_types=1);

namespace Modules\Discipline\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Discipline\Domain\Models\ReactivationRequest;

/**
 * طلب سحب طلب إعادة تفعيل — لمقدِّم الطلب نفسه قبل القرار.
 */
final class CancelReactivationRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var ReactivationRequest $reactivation */
        $reactivation = $this->route('reactivation');

        return $this->user()->can('cancel', $reactivation);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
