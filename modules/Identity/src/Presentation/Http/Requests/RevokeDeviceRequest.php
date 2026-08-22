<?php

declare(strict_types=1);

namespace Modules\Identity\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Identity\Domain\Models\UserDevice;

final class RevokeDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var UserDevice|null $device */
        $device = $this->route('device');

        return $device !== null
            && $this->user() !== null
            && $this->user()->can('revoke', $device);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
