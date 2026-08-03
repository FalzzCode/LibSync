<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReturnBorrowingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['admin', 'staff'], true);
    }

    public function rules(): array
    {
        return ['returned_at' => ['required', 'date', 'before_or_equal:today']];
    }

    public function messages(): array
    {
        return [
            'returned_at.before_or_equal' => 'Tanggal pengembalian tidak boleh setelah hari ini.',
        ];
    }
}
