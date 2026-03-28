<?php

namespace App\Http\Requests\PowerOffice;

use Illuminate\Foundation\Http\FormRequest;

class PowerOfficeOnboardingCallbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        $secret = config('poweroffice.callback_secret');
        if (! filled($secret)) {
            return true;
        }

        return hash_equals((string) $secret, (string) $this->header('X-PowerOffice-Callback-Secret', ''));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'state' => ['required', 'string', 'max:64'],
            'token' => ['nullable', 'string'],
            'onboardingToken' => ['nullable', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $token = $this->input('token') ?? $this->input('onboardingToken');
        if ($token !== null) {
            $this->merge(['token' => $token]);
        }
    }
}
