<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MemberRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $fields = ['name', 'class', 'address', 'phone', 'nis', 'nisn', 'major', 'email', 'account_email'];
        $normalized = [];

        foreach ($fields as $field) {
            if ($this->has($field)) {
                $value = trim((string) $this->input($field));
                $normalized[$field] = in_array($field, ['email', 'account_email'], true)
                    ? strtolower($value)
                    : $value;
            }
        }

        $this->merge($normalized);
    }

    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['admin', 'staff'], true);
    }

    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'class' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\-\s]+$/'],
            'nis' => ['nullable', 'string', 'max:50', Rule::unique('anggota', 'nis')->ignore($this->route('member'))],
            'nisn' => ['nullable', 'string', 'max:50', Rule::unique('anggota', 'nisn')->ignore($this->route('member'))],
            'major' => ['nullable', 'string', 'max:100'],
            'gender' => ['nullable', Rule::in(['male', 'female'])],
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('anggota', 'email')
                    ->where(fn ($query) => $query->whereNull('deleted_at'))
                    ->ignore($this->route('member')),
            ],
            'entry_year' => ['nullable', 'integer', 'between:1900,'.now()->year],
            'account_email' => ['nullable', 'email', 'max:255', Rule::unique('pengguna', 'email')->ignore($this->route('member')?->user_id)],
            'account_password' => ['nullable', 'string', 'min:8', 'required_with:account_email'],
        ];

        // Local student accounts are a development-only fallback. Do not
        // allow a crafted production request to create one while the form is
        // intentionally hidden and Google is the configured login method.
        if (! config('auth.local_login_enabled')) {
            $rules['account_email'] = ['prohibited'];
            $rules['account_password'] = ['prohibited'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama anggota wajib diisi.',
            'phone.regex' => 'Nomor telepon hanya boleh berisi angka, spasi, dan tanda + ( ) -.',
            'entry_year.integer' => 'Tahun masuk harus berupa angka.',
            'entry_year.between' => 'Tahun masuk harus berada antara 1900 dan '.now()->year.'.',
            'email.unique' => 'Email Google sudah dipakai anggota aktif. Jika email ada di arsip, pulihkan data anggota lama.',
            'account_email.unique' => 'Email login sudah dipakai akun lain.',
        ];
    }
}
