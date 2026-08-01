<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StudentActivationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nis' => ['required', 'string', 'max:50'],
            'activation_code' => ['required', 'string', 'max:32'],
        ];
    }
}
