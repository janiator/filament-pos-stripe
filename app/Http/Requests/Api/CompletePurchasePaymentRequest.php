<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class CompletePurchasePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // "deferred" is for creating unpaid pickup orders, not for settling them.
            'payment_method_code' => ['required', 'string', 'not_in:deferred'],
            'metadata' => ['nullable', 'array'],
            'pos_session_id' => ['nullable', 'integer', 'exists:pos_sessions,id'],
            'pos_device_id' => ['nullable', 'integer', 'exists:pos_devices,id'],
            'cart' => ['nullable', 'array'],
            'cart.items' => ['required_with:cart', 'array', 'min:1'],
            'cart.items.*.product_id' => ['required_with:cart', 'integer'],
            'cart.items.*.variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'cart.items.*.quantity' => ['required_with:cart', 'numeric', 'min:0.01'],
            'cart.items.*.unit_price' => ['required_with:cart', 'integer', 'min:0'],
            'cart.items.*.description' => ['nullable', 'string', 'max:500'],
            'cart.items.*.discount_amount' => ['nullable', 'integer', 'min:0'],
            'cart.items.*.discount_reason' => ['nullable', 'string', 'max:500'],
            'cart.items.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'cart.items.*.tax_inclusive' => ['nullable', 'boolean'],
            'cart.discounts' => ['nullable', 'array'],
            'cart.discounts.*.type' => ['nullable', 'string', 'max:50'],
            'cart.discounts.*.amount' => ['nullable', 'integer', 'min:0'],
            'cart.discounts.*.percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'cart.discounts.*.reason' => ['nullable', 'string', 'max:500'],
            'cart.total' => ['required_with:cart', 'integer', 'min:0'],
            'cart.subtotal' => ['nullable', 'integer', 'min:0'],
            'cart.total_tax' => ['nullable', 'integer', 'min:0'],
            'cart.total_discounts' => ['nullable', 'integer', 'min:0'],
            'cart.currency' => ['nullable', 'string', 'size:3'],
            'cart.customer_id' => ['nullable', 'integer'],
            'cart.customer_name' => ['nullable', 'string', 'max:255'],
            'cart.note' => ['nullable', 'string', 'max:1000'],
            'cart.tip_amount' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'payment_method_code.not_in' => 'The deferred payment method cannot be used to settle an existing deferred order. Choose cash, card, or another payment method.',
        ];
    }
}
