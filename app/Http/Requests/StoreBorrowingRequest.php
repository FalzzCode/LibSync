<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBorrowingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'member_id' => ['required', 'exists:members,id'],
            'book_id' => ['required', 'exists:books,id'],
            'borrowed_at' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:borrowed_at'],
        ];
    }

    public function messages(): array
    {
        return ['due_date.after_or_equal' => 'Tanggal jatuh tempo tidak boleh sebelum tanggal pinjam.'];
    }
}
