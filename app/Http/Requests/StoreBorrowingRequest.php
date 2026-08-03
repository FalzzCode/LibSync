<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBorrowingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['admin', 'staff'], true);
    }

    public function rules(): array
    {
        return [
            'member_id' => ['required', 'exists:anggota,id'],
            'book_id' => ['required', 'exists:buku,id'],
            'borrowed_at' => ['required', 'date', 'before_or_equal:today'],
            'due_date' => ['required', 'date', 'after_or_equal:borrowed_at'],
        ];
    }

    public function messages(): array
    {
        return [
            'borrowed_at.before_or_equal' => 'Tanggal pinjam tidak boleh setelah hari ini.',
            'due_date.after_or_equal' => 'Tanggal jatuh tempo tidak boleh sebelum tanggal pinjam.',
        ];
    }
}
