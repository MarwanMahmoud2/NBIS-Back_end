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
     * Normalize alternative field names to email / phone.
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
            'email'    => ['nullable', 'string', 'lowercase', 'email', 'max:255', 'required_without:phone'],
            'phone'    => ['nullable', 'string', 'max:15', 'required_without:email'],
            'password' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required_without'    => 'Email or phone is required.',
            'phone.required_without'    => 'Email or phone is required.',
        ];
    }
}
