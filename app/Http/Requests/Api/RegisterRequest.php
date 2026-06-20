<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * واجهة الموبايل قد تسمي الحقل "ID" — هو البريد الإلكتروني (email).
     */
    protected function prepareForValidation(): void
    {
        if ($this->filled('id') && ! $this->filled('email')) {
            $this->merge(['email' => $this->input('id')]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
            'phone' => ['nullable', 'string', 'max:20'],
            'national_id' => ['nullable', 'string', 'digits:14', 'unique:users,national_id'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Email is required (the app field labeled ID is the email address).',
            'email.unique' => 'This email is already registered.',
            'national_id.unique' => 'This national ID is already linked to another account.',
            'national_id.digits' => 'National ID must be exactly 14 digits.',
            'password.confirmed' => 'Password confirmation does not match.',
        ];
    }
}
