<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'class' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^[0-9+()\-\s]+$/'],
            'nis' => ['nullable', 'string', 'max:50', Rule::unique('members', 'nis')->ignore($this->route('member'))],
            'nisn' => ['nullable', 'string', 'max:50', Rule::unique('members', 'nisn')->ignore($this->route('member'))],
            'major' => ['nullable', 'string', 'max:100'],
            'gender' => ['nullable', Rule::in(['male', 'female'])],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('members', 'email')->ignore($this->route('member'))],
            'entry_year' => ['nullable', 'integer', 'between:1900,'.now()->year],
            'account_email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->route('member')?->user_id)],
            'account_password' => ['nullable', 'string', 'min:8', 'required_with:account_email'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama anggota wajib diisi.',
            'phone.required' => 'Nomor telepon wajib diisi.',
            'entry_year.integer' => 'Tahun masuk harus berupa angka.',
            'entry_year.between' => 'Tahun masuk harus berada antara 1900 dan '.now()->year.'.',
        ];
    }
}
