<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
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
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255'],
            'password' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Email is required (the app field labeled ID is the email address).',
        ];
    }
}
