<?php

namespace App\Http\Requests\Api\V1;

use App\Services\PaymentService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InitiatePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Ownership réelle vérifiée dans le contrôleur (propriétaire
        // authentifié ou preuve par téléphone) — pas exprimable ici.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'provider' => ['required', 'string', Rule::in(PaymentService::SUPPORTED_PROVIDERS)],
        ];
    }
}
