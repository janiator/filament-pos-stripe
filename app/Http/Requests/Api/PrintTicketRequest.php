<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class PrintTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'printer_id' => ['required', 'integer', 'min:1'],
            'order_number' => ['required', 'string', 'max:255'],
            'date' => ['required', 'string', 'max:255'],
            'place' => ['required', 'string', 'max:255'],
            'heading' => ['nullable', 'string', 'max:255'],
            'confirmation_url' => ['nullable', 'string', 'max:2048'],
            'amount_paid' => ['nullable', 'numeric', 'min:0'],
            'tickets' => ['required', 'array', 'min:1'],
            'tickets.*.category' => ['required', 'string', 'max:255'],
            'tickets.*.section' => ['nullable', 'string', 'max:255'],
            'tickets.*.row' => ['nullable', 'string', 'max:255'],
            'tickets.*.seat' => ['required', 'string', 'max:255'],
            'tickets.*.entrance' => ['nullable', 'string', 'max:255'],
            'tickets.*.ticket_price' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
