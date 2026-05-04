<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MercadoPagoWebhookRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Los webhooks de Mercado Pago no requieren autenticación
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'in:payment,merchant_order,plan,subscription,invoice,point'],
            'data' => ['required', 'array'],
            'data.id' => ['required', 'numeric'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'type.required' => 'El tipo de notificación es requerido',
            'type.in' => 'Tipo de notificación no válido',
            'data.required' => 'Los datos de la notificación son requeridos',
            'data.id.required' => 'El ID de la notificación es requerido',
        ];
    }
}
