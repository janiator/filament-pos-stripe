<?php

namespace App\Http\Requests\PowerOffice;

use Illuminate\Foundation\Http\FormRequest;

class InitPowerOfficeOnboardingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
