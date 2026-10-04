@extends('layouts.app')

@section('page-title')
    <h2 class="page-title">Users</h2>
@endsection

@section('content')
<div class="page-actions">
    <a href="{{ route('users.create') }}" class="btn-primary">+ Add User</a>
</div>

<p class="hint" style="margin-bottom:1rem;">
    Staff can add records and edit their own. Admins can also manage templates,
    settings and these accounts. Accounts are never deleted, so the names on old
    records stay intact; switch <strong>Can sign in</strong> off to withdraw access.
</p>

<div class="table-wrap only-desktop">
    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Role</th>
                <th>Records</th>
                <th>Can sign in</th>
                <th class="align-right">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($users as $user)
                <tr>
                    <td>
                        <strong>{{ $user->name }}</strong>
                        @if (auth()->id() === $user->id)
                            <span class="badge badge-blue">You</span>
                        @endif
                    </td>
                    <td><span class="mono">{{ $user->email }}</span></td>
                    <td>
                        <span class="badge {{ $user->isAdmin() ? 'badge-blue' : '' }}">{{ ucfirst($user->role) }}</span>
                    </td>
                    <td>{{ $user->patient_records_count }}</td>
                    <td>
                        @if ($user->is_active)
                            <span class="badge badge-green">Active</span>
                        @else
                            <span class="badge badge-red">Disabled</span>
                        @endif
                    </td>
                    <td class="align-right">
                        <span class="row-actions">
                            <a href="{{ route('users.edit', $user) }}">Edit</a>
                        </span>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="table-empty">No users found.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="only-mobile">
    @forelse ($users as $user)
        <div class="card">
            <div class="page-head card-head">
                <strong>{{ $user->name }}</strong>
                @if ($user->is_active)
                    <span class="badge badge-green">Active</span>
                @else
                    <span class="badge badge-red">Disabled</span>
                @endif
            </div>
            <div class="meta-line">
                {{ $user->email }} &middot; {{ ucfirst($user->role) }}
                &middot; {{ $user->patient_records_count }} {{ Str::plural('record', $user->patient_records_count) }}
            </div>
            <div>
                <span class="row-actions">
                    <a href="{{ route('users.edit', $user) }}">Edit</a>
                </span>
            </div>
        </div>
    @empty
        <div class="card table-empty">No users found.</div>
    @endforelse
</div>

{{ $users->links() }}
@endsection