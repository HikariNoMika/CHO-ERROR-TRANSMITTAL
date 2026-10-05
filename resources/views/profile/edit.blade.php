@extends('layouts.app')

@section('page-title')
    <h2 class="page-title">My Profile</h2>
@endsection

@section('content')
<div class="form-wrap narrow">
    <form method="POST" action="{{ route('profile.update') }}">
        @csrf
        @method('PUT')

        <div class="card">
            <div class="card-head">
                <h3>{{ $user->name }}</h3>
                <p class="hint">
                    {{ ucfirst($user->role) }} &middot; Joined {{ $user->created_at?->format('M j, Y') }}
                    &middot; {{ $user->patient_records_count }} {{ Str::plural('record', $user->patient_records_count) }}
                </p>
            </div>

            <div class="fields">
                <div class="field">
                    <label for="name">Full name *</label>
                    <input type="text" name="name" id="name" value="{{ old('name', $user->name) }}" required autofocus autocomplete="name">
                    @error('name')<p class="fielderror">{{ $message }}</p>@enderror
                </div>

                <div class="field">
                    <label for="email">Email address *</label>
                    <input type="email" name="email" id="email" value="{{ old('email', $user->email) }}" required autocomplete="email">
                    <p class="hint">You sign in with this address, so keep it somewhere you can reach.</p>
                    @error('email')<p class="fielderror">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-head">
                <h3>Change password</h3>
                <p class="hint">Leave both boxes empty to keep your current password.</p>
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
        </div>
    </form>
</div>
@endsection