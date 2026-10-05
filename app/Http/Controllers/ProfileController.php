<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function __construct(protected AuditLogService $auditLog) {}

    /**
     * Show the signed-in user's own details.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the signed-in user's own details.
     *
     * Role and active state are intentionally not editable here - an admin
     * manages those from the Users pages. Only the fields the request validated
     * are written, so a crafted POST carrying role/is_active is ignored rather
     * than granting itself admin.
     *
     * A blank password means "leave it alone", so an accidental empty field
     * cannot lock yourself out of your own account.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validated();

        $changed = [];

        if ($data['name'] !== $user->name) {
            $changed[] = 'name';
        }
        if ($data['email'] !== $user->email) {
            $changed[] = 'email';
        }

        // Drop the empty-string password so the model's hashed cast is not
        // handed a blank value.
        if (empty($data['password'])) {
            unset($data['password']);
        } else {
            $changed[] = 'password';
        }

        $user->fill($data)->save();

        if ($changed !== []) {
            $this->auditLog->logProfileUpdated(
                $user,
                'Updated own profile: '.implode(', ', $changed)
            );
        }

        return redirect()
            ->route('profile.edit')
            ->with('success', 'Your profile has been updated.');
    }
}
