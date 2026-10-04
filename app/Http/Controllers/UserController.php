<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserRequest;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct(protected AuditLogService $auditLog) {}

    public function index(): View
    {
        Gate::authorize('viewAny', User::class);

        $users = User::withCount('patientRecords')
            ->orderByRaw("CASE WHEN role = 'admin' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->paginate(25);

        return view('users.index', compact('users'));
    }

    public function create(): View
    {
        Gate::authorize('create', User::class);

        return view('users.create');
    }

    public function store(UserRequest $request): RedirectResponse
    {
        Gate::authorize('create', User::class);

        $user = User::create($request->safe()->only('name', 'email', 'role', 'is_active', 'password'));

        $this->auditLog->log(
            'created_user',
            "Created {$user->role} account for {$user->name}",
            User::class,
            $user->id
        );

        return redirect()
            ->route('users.index')
            ->with('success', $user->name.' can now sign in.');
    }

    public function edit(User $user): View
    {
        Gate::authorize('update', $user);

        return view('users.edit', compact('user'));
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        Gate::authorize('update', $user);

        // The two self-lockout guards live in the policy, so they are enforced
        // here rather than in the form, where a crafted POST could skip them.
        // Turning access OFF is the guarded direction; turning it back on is an
        // ordinary update.
        if (! $request->boolean('is_active') && $user->is_active) {
            Gate::authorize('deactivate', $user);
        }

        if ($request->input('role') !== $user->role) {
            Gate::authorize('changeRole', $user);
        }

        $data = $request->safe()->only(['name', 'email', 'role', 'is_active']);

        // A blank password field means "leave it alone".
        if ($request->filled('password')) {
            $data['password'] = $request->input('password');
        }

        $user->update($data);

        $this->auditLog->log(
            'updated_user',
            "Updated {$user->role} account for {$user->name}",
            User::class,
            $user->id
        );

        return redirect()
            ->route('users.index')
            ->with('success', $user->name."'s account was updated.");
    }
}
