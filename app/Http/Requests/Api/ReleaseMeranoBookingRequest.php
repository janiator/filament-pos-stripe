<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class ReleaseMeranoBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pos_session_id' => ['nullable', 'integer'],
            'pos_device_id' => ['nullable', 'integer'],
        ];
    }
}
