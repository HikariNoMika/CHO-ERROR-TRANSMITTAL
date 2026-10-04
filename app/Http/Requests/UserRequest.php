<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * On create a password is required; on edit the field is left blank to mean
     * "keep the current one", so a typo cannot quietly reset someone's login.
     */
    public function rules(): array
    {
        $user = $this->route('user');
        $editing = $user instanceof User;

        return [
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($editing ? $user->id : null),
            ],
            'role' => ['required', Rule::in(['admin', 'staff'])],
            'is_active' => ['required', 'boolean'],
            'password' => [
                $editing ? 'nullable' : 'required',
                'confirmed',
                Password::min(8)->letters()->numbers(),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Name is required.',
            'email.required' => 'Email is required.',
            'email.email' => 'Please enter a valid email address.',
            'email.unique' => 'That email address is already in use.',
            'role.required' => 'Please choose a role.',
            'role.in' => 'Role must be either admin or staff.',
            'is_active.required' => 'Please choose whether the account can sign in.',
            'password.required' => 'A password is required for a new user.',
            'password.confirmed' => 'The two passwords do not match.',
            'password.min' => 'The password must be at least 8 characters.',
        ];
    }
}
