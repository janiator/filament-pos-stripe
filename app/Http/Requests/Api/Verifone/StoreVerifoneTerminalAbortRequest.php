<?php

namespace App\Http\Requests\Api\Verifone;

use Illuminate\Foundation\Http\FormRequest;

class StoreVerifoneTerminalAbortRequest extends FormRequest
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
            'service_id' => ['required', 'string', 'max:64'],
            'sale_id' => ['required', 'string', 'max:64'],
        ];
    }
}
