<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminUpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_platform_admin;
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($this->boolean('is_platform_admin') && $this->boolean('is_accountant')) {
                $validator->errors()->add(
                    'is_accountant',
                    'Un compte ne peut pas être à la fois administrateur plateforme et comptable cabinet.'
                );
            }
        });
    }

    public function rules(): array
    {
        $user = $this->route('user');
        $userId = $user->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'company_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'sector' => ['nullable', 'string', 'max:255'],
            'rccm' => ['nullable', 'string', 'max:255'],
            'is_platform_admin' => ['boolean'],
            'is_accountant' => ['boolean'],
            'account_suspended' => ['boolean'],
            'suspended_reason' => ['nullable', 'string', 'max:2000'],
            'is_premium' => ['boolean'],
            'premium_status' => ['nullable', 'string', 'max:32'],
            'premium_ends_at' => ['nullable', 'date'],
        ];
    }
}
