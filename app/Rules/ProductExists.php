<?php

namespace App\Rules;

use Closure;
use App\Models\Product;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Validation\ValidationRule;

class ProductExists implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $product = Product::where('name', $value)->where('company_id', request()->company_id)->get();

        if ($product->isNotEmpty()) {
            $fail("El producto {$value} ya existe en la base de datos");
        }
    }
}
