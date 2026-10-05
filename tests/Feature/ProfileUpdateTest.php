<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role = 'staff', array $overrides = []): User
    {
        $user = User::factory()->create($overrides + [
            'password' => Hash::make('secret123'),
            'is_active' => true,
        ]);

        $user->forceFill(['role' => $role])->save();

        return $user->fresh();
    }

    public function test_the_profile_page_is_reachable_for_a_signed_in_user(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee($user->name)
            ->assertSee($user->email);
    }

    public function test_the_profile_page_requires_signing_in(): void
    {
        $this->get(route('profile.edit'))->assertRedirect(route('login'));
    }

    public function test_a_staff_user_can_update_their_own_name_and_email(): void
    {
        $user = $this->makeUser('staff');

        $this->actingAs($user)
            ->put(route('profile.update'), [
                'name' => 'Renamed Person',
                'email' => 'renamed@example.com',
            ])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Renamed Person',
            'email' => 'renamed@example.com',
        ]);
    }

    public function test_an_admin_can_also_update_their_own_profile(): void
    {
        $user = $this->makeUser('admin');

        $this->actingAs($user)
            ->put(route('profile.update'), [
                'name' => 'Admin Renamed',
                'email' => 'admin2@example.com',
            ])
            ->assertRedirect(route('profile.edit'));

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Admin Renamed',
        ]);
    }

    public function test_saving_unchanged_details_does_not_fail_on_the_unique_email_rule(): void
    {
        $user = $this->makeUser();

        // The unique rule must ignore the user's own row, otherwise simply
        // opening and re-saving the form would be impossible.
        $this->actingAs($user)
            ->put(route('profile.update'), [
                'name' => $user->name,
                'email' => $user->email,
            ])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHasNoErrors();
    }

    public function test_an_email_already_used_by_another_user_is_rejected(): void
    {
        $user = $this->makeUser('staff');
        $other = $this->makeUser('staff', ['email' => 'taken@example.com']);

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->put(route('profile.update'), [
                'name' => $user->name,
                'email' => 'taken@example.com',
            ])
            ->assertSessionHasErrors('email');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => $user->email,
        ]);
    }

    public function test_a_blank_password_keeps_the_current_one(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->put(route('profile.update'), [
                'name' => 'Same Password Kept',
                'email' => $user->email,
                'password' => '',
            ])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHasNoErrors();

        $this->assertTrue(
            Hash::check('secret123', (string) $user->fresh()->getRawOriginal('password')),
            'A blank password field must not reset the password.'
        );
    }

    public function test_a_new_password_is_hashed_and_takes_effect(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->put(route('profile.update'), [
                'name' => $user->name,
                'email' => $user->email,
                'password' => 'brandnew99',
                'password_confirmation' => 'brandnew99',
            ])
            ->assertRedirect(route('profile.edit'));

        $stored = (string) $user->fresh()->getRawOriginal('password');

        $this->assertTrue(Hash::check('brandnew99', $stored));
        $this->assertFalse(Hash::check('secret123', $stored), 'The old password should no longer work.');
        $this->assertStringStartsWith('$2y$', $stored, 'The password must be stored as a bcrypt hash.');
    }

    public function test_mismatched_password_confirmation_is_rejected(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->put(route('profile.update'), [
                'name' => $user->name,
                'email' => $user->email,
                'password' => 'brandnew99',
                'password_confirmation' => 'different99',
            ])
            ->assertSessionHasErrors('password');

        $this->assertTrue(
            Hash::check('secret123', (string) $user->fresh()->getRawOriginal('password'))
        );
    }

    public function test_a_weak_password_is_rejected(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->put(route('profile.update'), [
                'name' => $user->name,
                'email' => $user->email,
                'password' => 'short',
                'password_confirmation' => 'short',
            ])
            ->assertSessionHasErrors('password');
    }

    public function test_a_user_cannot_change_their_own_role(): void
    {
        $user = $this->makeUser('staff');

        // role and is_active are absent from ProfileUpdateRequest::rules(), so a
        // crafted POST carrying them must be ignored rather than honoured.
        $this->actingAs($user)
            ->put(route('profile.update'), [
                'name' => $user->name,
                'email' => $user->email,
                'role' => 'admin',
                'is_active' => '1',
            ])
            ->assertRedirect(route('profile.edit'));

        $this->assertSame('staff', $user->fresh()->role);
    }

    public function test_a_user_cannot_reactivate_themselves_or_another_account(): void
    {
        $staff = $this->makeUser('staff');
        $disabledAdmin = $this->makeUser('admin', ['is_active' => false]);

        $this->actingAs($staff)
            ->put(route('profile.update'), [
                'name' => $staff->name,
                'email' => $staff->email,
                'role' => 'admin',
            ])
            ->assertRedirect(route('profile.edit'));

        $this->assertSame('staff', $staff->fresh()->role);
        $this->assertFalse((bool) $disabledAdmin->fresh()->is_active);
    }

    public function test_an_update_is_written_to_the_audit_log(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->put(route('profile.update'), [
                'name' => 'Audited Change',
                'email' => $user->email,
            ])
            ->assertRedirect(route('profile.edit'));

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'updated_profile',
        ]);
    }

    public function test_an_update_that_changes_nothing_is_not_logged(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->put(route('profile.update'), [
                'name' => $user->name,
                'email' => $user->email,
            ])
            ->assertRedirect(route('profile.edit'));

        $this->assertDatabaseMissing('audit_logs', [
            'user_id' => $user->id,
            'action' => 'updated_profile',
        ]);
    }

    public function test_the_profile_route_has_no_user_id_in_it(): void
    {
        // Editing another person's profile must not be expressible in the URL.
        $this->assertSame(config('app.url').'/profile', route('profile.edit'));
        $this->assertSame(config('app.url').'/profile', route('profile.update'));
    }
}
