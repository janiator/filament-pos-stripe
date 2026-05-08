<?php

namespace App\Http\Requests\Api\Verifone;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreVerifonePaymentRequest extends FormRequest
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
            'terminal_id' => ['nullable', 'integer', 'exists:verifone_terminals,id'],
            'terminal_poiid' => ['nullable', 'string', 'max:128'],
            'amount' => ['required', 'integer', 'min:1'],
            'currency' => ['nullable', 'string', 'size:3'],
            'description' => ['nullable', 'string', 'max:255'],
            'service_id' => ['nullable', 'string', 'max:64'],
            'sale_id' => ['nullable', 'string', 'max:64'],
            'operator_id' => ['nullable', 'string', 'max:64'],
            'pos_session_id' => ['nullable', 'integer', 'exists:pos_sessions,id'],
            'pos_device_id' => ['nullable', 'integer', 'exists:pos_devices,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $hasTerminalId = filled($this->input('terminal_id'));
            $hasTerminalPoiid = filled($this->input('terminal_poiid'));

            if (! $hasTerminalId && ! $hasTerminalPoiid) {
                $validator->errors()->add('terminal_id', 'Either terminal_id or terminal_poiid is required.');
            }
        });
    }
}
