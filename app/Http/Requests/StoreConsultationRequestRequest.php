<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreConsultationRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasRole('abogado') && $this->user()->hasActiveSubscription();
    }

    public function rules(): array
    {
        return [
            'document_type' => ['required', 'in:CC,CE,PA,NIT'],
            'document_number' => ['required', 'string', 'max:30'],
            'full_name' => ['nullable', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'issuance_date' => [
                'nullable',
                'date_format:Y-m-d',
                // Obligatoria solo si se pide RNMC y el documento es Cédula de Ciudadanía
                'required_if:document_type,CC',
            ],
            'sites' => ['required', 'array', 'min:1'],
            'sites.*' => ['in:comptroller,judicial_police,rnmc,attorney_general'],
        ];
    }
}
