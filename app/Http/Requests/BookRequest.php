<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Cover wajib diisi cuma pas tambah buku baru (POST). Pas edit (PUT), boleh kosong = cover gak diganti.
        $isCreate = $this->isMethod('post');

        return [
            'title' => ['required', 'string', 'max:255'],
            'author' => ['required', 'string', 'max:255'],
            'book_code' => ['nullable', 'string', 'max:100', Rule::unique('books', 'book_code')->ignore($this->route('book'))],
            'isbn' => ['nullable', 'string', 'max:30'],
            'publisher' => ['nullable', 'string', 'max:255'],
            'publication_year' => ['nullable', 'digits:4', 'integer', 'between:1000,'.now()->year],
            'category_id' => ['required', 'exists:categories,id'],
            'stock' => ['required', 'integer', 'min:0'],
            'shelf' => ['nullable', 'string', 'max:100'],
            'language' => ['nullable', 'string', 'max:50'],
            'page_count' => ['nullable', 'integer', 'min:1', 'max:99999'],
            'description' => ['nullable', 'string', 'max:5000'],
            'cover_image' => [$isCreate ? 'required' : 'nullable', 'image', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Judul buku wajib diisi.',
            'author.required' => 'Penulis wajib diisi.',
            'category_id.required' => 'Kategori wajib dipilih.',
            'category_id.exists' => 'Kategori tidak valid.',
            'stock.required' => 'Stok wajib diisi.',
            'stock.integer' => 'Stok harus berupa angka.',
            'page_count.integer' => 'Jumlah halaman harus berupa angka.',
            'page_count.min' => 'Jumlah halaman minimal 1.',
            'cover_image.required' => 'Cover buku wajib diupload.',
            'cover_image.image' => 'File cover harus berupa gambar.',
        ];
    }
}
