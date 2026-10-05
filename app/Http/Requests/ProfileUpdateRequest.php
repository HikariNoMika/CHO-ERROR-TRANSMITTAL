<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Self-service profile edits.
 *
 * Deliberately narrower than UserRequest: a user may change their own name,
 * email and password, but never their role or active state. Those two belong to
 * an administrator, otherwise anyone could promote themselves or re-enable a
 * deactivated account from this form.
 */
class ProfileUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Acting on yourself, which the profile route guarantees by construction:
        // the controller resolves the record from the session, never from input.
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $user = $this->user();

        return [
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                'max:255',
                // Ignore your own row, otherwise saving unchanged details would
                // collide with your existing address.
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'password' => [
                'nullable',
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
            'password.confirmed' => 'The two passwords do not match.',
            'password.min' => 'The password must be at least 8 characters.',
        ];
    }
}
