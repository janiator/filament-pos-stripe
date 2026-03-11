<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class CheckMeranoAvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'seats' => ['required', 'array', 'min:1'],
            'seats.*' => ['required', 'string', 'max:255'],
            'pos_device_id' => ['nullable', 'integer'],
        ];
    }
}
