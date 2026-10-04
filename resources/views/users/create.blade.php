@extends('layouts.app')

@section('page-title')
    <h2 class="page-title">Add User</h2>
@endsection

@section('content')
<div class="form-wrap narrow">
    <form method="POST" action="{{ route('users.store') }}">
        @csrf

        <div class="card">
            <div class="card-head">
                <h3>New account</h3>
                <p class="hint">The person signs in with this email address and password.</p>
            </div>

            <div class="fields">
                <div class="field">
                    <label for="name">Full name *</label>
                    <input type="text" name="name" id="name" value="{{ old('name') }}" required autofocus>
                    @error('name')<p class="fielderror">{{ $message }}</p>@enderror
                </div>

                <div class="field">
                    <label for="email">Email address *</label>
                    <input type="email" name="email" id="email" value="{{ old('email') }}" required>
                    @error('email')<p class="fielderror">{{ $message }}</p>@enderror
                </div>

                <div class="field">
                    <label for="role">Role *</label>
                    <select name="role" id="role" required>
                        <option value="staff" @selected(old('role', 'staff') === 'staff')>Staff</option>
                        <option value="admin" @selected(old('role') === 'admin')>Admin</option>
                    </select>
                    <p class="hint">
                        Staff can add and edit their own records. Admin can also manage templates,
                        settings and user accounts.
                    </p>
                    @error('role')<p class="fielderror">{{ $message }}</p>@enderror
                </div>

                <div class="field">
                    <label for="is_active">Can sign in</label>
                    <select name="is_active" id="is_active" required>
                        <option value="1" @selected(old('is_active', '1') === '1')>Yes</option>
                        <option value="0" @selected(old('is_active') === '0')>No</option>
                    </select>
                    @error('is_active')<p class="fielderror">{{ $message }}</p>@enderror
                </div>

                <div class="field">
                    <label for="password">Password *</label>
                    <input type="password" name="password" id="password" required autocomplete="new-password">
                    <p class="hint">At least 8 characters, with letters and numbers.</p>
                    @error('password')<p class="fielderror">{{ $message }}</p>@enderror
                </div>

                <div class="field">
                    <label for="password_confirmation">Confirm password *</label>
                    <input type="password" name="password_confirmation" id="password_confirmation" required autocomplete="new-password">
                    @error('password_confirmation')<p class="fielderror">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>

        <div class="actions">
            <button type="submit" class="btn-primary">Create account</button>
            <a href="{{ route('users.index') }}" class="btn-secondary">Cancel</a>
        </div>
    </form>
</div>
@endsection