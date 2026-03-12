<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmMeranoBookingPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount_paid_ore' => ['required', 'integer', 'min:0'], // 0 allowed for freeticket/zero-total orders
            'pos_charge_id' => ['required', 'string', 'max:255'],
            'pos_session_id' => ['nullable', 'integer'],
            'currency' => ['required', 'string', 'max:10'],
            'pos_device_id' => ['nullable', 'integer'],
        ];
    }
}
