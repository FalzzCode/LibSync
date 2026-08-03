<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'email' => strtolower(trim((string) $this->input('email'))),
        ]);
    }

    public function authorize(): bool
    {
        // User management is an admin-only operation. Keep this guard here
        // as a second line of defense in case the route middleware changes.
        return $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        // Password wajib cuma pas tambah baru (POST). Email unik, tapi diabaikan buat user yang sedang diedit sendiri.
        $isCreate = $this->isMethod('post');
        $userId = $this->route('user')?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('pengguna', 'email')->ignore($userId)],
            'password' => [$isCreate ? 'required' : 'nullable', 'string', 'min:8'],
            'role' => ['required', 'in:admin,staff'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama wajib diisi.',
            'email.required' => 'Email wajib diisi.',
            'email.unique' => 'Email sudah digunakan akun lain.',
            'password.required' => 'Password wajib diisi.',
            'password.min' => 'Password minimal 8 karakter.',
            'role.required' => 'Peran wajib dipilih.',
        ];
    }
}
