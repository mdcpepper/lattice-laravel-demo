<?php

namespace App\Http\Requests\Cart;

use App\Models\Product;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AddToCartRequest extends FormRequest
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
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'product' => [
                'required',
                'integer',
                function (
                    string $attribute,
                    mixed $value,
                    Closure $fail,
                ): void {
                    if (
                        ! is_numeric($value) ||
                        ! Product::query()->whereKey((int) $value)->exists()
                    ) {
                        $fail('The selected product is unavailable.');
                    }
                },
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'product.required' => 'Please choose a product to add to your cart.',
            'product.integer' => 'The selected product is invalid.',
        ];
    }
}
