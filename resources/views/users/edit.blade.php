@extends('layouts.app')

@section('page-title')
    <h2 class="page-title">Edit User</h2>
@endsection

@section('content')
<div class="form-wrap narrow">
    <form method="POST" action="{{ route('users.update', $user) }}">
        @csrf
        @method('PUT')

        <div class="card">
            <div class="card-head">
                <h3>{{ $user->name }}</h3>
                <p class="hint">Joined {{ $user->created_at?->format('M j, Y') }} &middot; {{ $user->patient_records_count }} {{ Str::plural('record', $user->patient_records_count) }}</p>
            </div>

            <div class="fields">
                <div class="field">
                    <label for="name">Full name *</label>
                    <input type="text" name="name" id="name" value="{{ old('name', $user->name) }}" required autofocus>
                    @error('name')<p class="fielderror">{{ $message }}</p>@enderror
                </div>

                <div class="field">
                    <label for="email">Email address *</label>
                    <input type="email" name="email" id="email" value="{{ old('email', $user->email) }}" required>
                    @error('email')<p class="fielderror">{{ $message }}</p>@enderror
                </div>

                <div class="field">
                    <label for="role">Role *</label>
                    <select name="role" id="role" required>
                        <option value="staff" @selected(old('role', $user->role) === 'staff')>Staff</option>
                        <option value="admin" @selected(old('role', $user->role) === 'admin')>Admin</option>
                    </select>
                    {{-- Guards are enforced in the policy, so this only explains
                         the rule rather than pretending to enforce it. --}}
                    @if (auth()->id() === $user->id)
                        <p class="hint">You cannot change your own role &mdash; that could lock everyone out of this page.</p>
                    @endif
                    @error('role')<p class="fielderror">{{ $message }}</p>@enderror
                </div>

                <div class="field">
                    <label for="is_active">Can sign in</label>
                    <select name="is_active" id="is_active" required>
                        <option value="1" @selected(old('is_active', (string) (int) $user->is_active) === '1')>Yes</option>
                        <option value="0" @selected(old('is_active', (string) (int) $user->is_active) === '0')>No</option>
                    </select>
                    <p class="hint">Accounts are never deleted: their name stays on the records they created. Turn access off instead.</p>
                    @error('is_active')<p class="fielderror">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-head">
                <h3>Reset password</h3>
                <p class="hint">Leave both boxes empty to keep the current password.</p>
            </div>

            <div class="fields">
                <div class="field">
                    <label for="password">New password</label>
                    <input type="password" name="password" id="password" autocomplete="new-password">
                    <p class="hint">At least 8 characters, with letters and numbers.</p>
                    @error('password')<p class="fielderror">{{ $message }}</p>@enderror
                </div>

                <div class="field">
                    <label for="password_confirmation">Confirm new password</label>
                    <input type="password" name="password_confirmation" id="password_confirmation" autocomplete="new-password">
                    @error('password_confirmation')<p class="fielderror">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>

        <div class="actions">
            <button type="submit" class="btn-primary">Save changes</button>
            <a href="{{ route('users.index') }}" class="btn-secondary">Cancel</a>
        </div>
    </form>
</div>
@endsection