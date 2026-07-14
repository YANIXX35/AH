<?php

namespace App\Http\Requests;

use App\Support\ClientWorkspace;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Règles partagées entre création et édition d'un produit de stock. Le nom
 * doit être unique par PME (workspace), en excluant le produit courant lors
 * d'une édition — d'où la résolution dynamique de l'id via la route.
 */
class StockProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $product = $this->route('product');

        return [
            'sku' => ['nullable', 'string', 'max:60'],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('stock_products', 'name')
                    ->where('user_id', ClientWorkspace::effectiveUserId())
                    ->ignore($product?->id),
            ],
            'unit' => ['nullable', 'string', 'max:30'],
            'sale_price' => ['nullable', 'numeric', 'min:0'],
            'reorder_threshold' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'Un produit avec ce nom existe déjà.',
        ];
    }
}
