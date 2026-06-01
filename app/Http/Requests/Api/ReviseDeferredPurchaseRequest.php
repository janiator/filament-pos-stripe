<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class ReviseDeferredPurchaseRequest extends FormRequest
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
            'metadata' => ['nullable', 'array'],
            'pos_session_id' => ['nullable', 'integer', 'exists:pos_sessions,id'],
            'pos_device_id' => ['nullable', 'integer', 'exists:pos_devices,id'],
            'cart' => ['required', 'array'],
            'cart.items' => ['required', 'array', 'min:1'],
            'cart.items.*.product_id' => ['required', 'integer'],
            'cart.items.*.variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'cart.items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'cart.items.*.unit_price' => ['required', 'integer', 'min:0'],
            'cart.items.*.description' => ['nullable', 'string', 'max:500'],
            'cart.items.*.discount_amount' => ['nullable', 'integer', 'min:0'],
            'cart.items.*.discount_reason' => ['nullable', 'string', 'max:500'],
            'cart.items.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'cart.items.*.tax_inclusive' => ['nullable', 'boolean'],
            'cart.items.*.metadata' => ['nullable', 'array'],
            'cart.discounts' => ['nullable', 'array'],
            'cart.discounts.*.type' => ['nullable', 'string', 'max:50'],
            'cart.discounts.*.amount' => ['nullable', 'integer', 'min:0'],
            'cart.discounts.*.percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'cart.discounts.*.reason' => ['nullable', 'string', 'max:500'],
            'cart.total' => ['required', 'integer', 'min:0'],
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
}
