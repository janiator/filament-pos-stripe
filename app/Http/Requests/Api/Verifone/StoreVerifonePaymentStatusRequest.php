<?php

namespace App\Http\Requests\Api\Verifone;

use Illuminate\Foundation\Http\FormRequest;

class StoreVerifonePaymentStatusRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'message_reference_service_id' => ['nullable', 'string', 'max:64'],
            'message_reference_sale_id' => ['nullable', 'string', 'max:64'],
            'message_reference_poiid' => ['nullable', 'string', 'max:128'],
        ];
    }
}
