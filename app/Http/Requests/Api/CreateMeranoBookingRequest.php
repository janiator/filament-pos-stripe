<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class CreateMeranoBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'event_id' => ['required', 'integer'],
            'seats' => ['required', 'array', 'min:1'],
            'seats.*' => ['required', 'string', 'max:255'],
            'customer_id' => ['nullable', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'payment_type' => ['required', 'string', 'in:pos'],
            'pos_device_id' => ['nullable', 'integer'],
        ];
    }
}
