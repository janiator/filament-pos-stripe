<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class PrintFreeTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'printer_id' => ['required', 'integer'],
            'date' => ['nullable', 'string', 'max:255'],
            'place' => ['nullable', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'integer', 'min:1'],
            'discount' => ['nullable', 'string', 'max:255'],
            'applies_to' => ['nullable', 'string', 'max:255'],
            'max_tickets' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
