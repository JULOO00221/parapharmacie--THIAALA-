<?php

namespace App\Http\Requests\Api\V1;

use App\Services\OrderService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates SHAPE and FORMAT only (types, presence, ranges, the
 * payment_method whitelist). Existence and business-state checks (does
 * the product/store/zone really exist, is it active, is there enough
 * stock) stay OrderService's exclusive responsibility — it remains the
 * single source of truth, callable safely outside HTTP too.
 */
class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        // POST /orders accepte aussi bien un visiteur qu'un utilisateur
        // authentifié — aucune restriction d'accès ici.
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'idempotency_key' => $this->header('Idempotency-Key'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'min:1'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:50'],

            'store_id' => ['required', 'integer', 'min:1'],

            'customer_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:255'],
            'customer_email' => ['nullable', 'email', 'max:255'],

            'is_pickup' => ['required', 'boolean'],
            'delivery_zone_id' => ['nullable', 'integer', 'min:1'],
            'delivery_address' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:1000'],

            'payment_method' => ['required', 'string', Rule::in(OrderService::PAYMENT_METHODS)],

            // Lu depuis l'en-tête Idempotency-Key (voir prepareForValidation).
            // Bornes volontairement strictes : jamais une valeur
            // arbitrairement longue (ex. un UUID v4 fait 36 caractères).
            'idempotency_key' => ['required', 'string', 'min:16', 'max:128', 'regex:/^[A-Za-z0-9\-_.]+$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'idempotency_key.required' => 'L\'en-tête Idempotency-Key est obligatoire.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $isPickup = $this->boolean('is_pickup');

            if ($isPickup) {
                if ($this->filled('delivery_zone_id')) {
                    $validator->errors()->add(
                        'delivery_zone_id',
                        'Aucune zone de livraison ne doit être fournie pour un retrait en boutique.'
                    );
                }

                return;
            }

            if (! $this->filled('delivery_zone_id')) {
                $validator->errors()->add(
                    'delivery_zone_id',
                    'La zone de livraison est obligatoire lorsque ce n\'est pas un retrait en boutique.'
                );
            }

            if (! $this->filled('delivery_address')) {
                $validator->errors()->add(
                    'delivery_address',
                    'L\'adresse de livraison est obligatoire lorsque ce n\'est pas un retrait en boutique.'
                );
            }
        });
    }
}
