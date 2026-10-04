<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->makeUser('admin');
    }

    /**
     * The factory sets no role or active flag, so they are written after
     * creation: is_active is cast to boolean, and a cast attribute written
     * through create() would be coerced to 1 and hide the intent.
     */
    protected function makeUser(string $role, bool $active = true): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role, 'is_active' => $active])->save();

        return $user;
    }

    protected function staff(): User
    {
        return $this->makeUser('staff');
    }

    protected function otherAdmin(): User
    {
        return $this->makeUser('admin');
    }

    /** A valid payload; individual tests override single fields. */
    protected function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'JUAN DELA CRUZ',
            'email' => 'juan@example.com',
            'role' => 'staff',
            'is_active' => '1',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ], $overrides);
    }

    public function test_the_sidebar_shows_users_to_an_admin(): void
    {
        $response = $this->actingAs($this->admin)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee(route('users.index'), false);
    }

    public function test_the_sidebar_hides_users_from_staff(): void
    {
        $response = $this->actingAs($this->staff())->get(route('dashboard'));

        $response->assertOk();
        $response->assertDontSee(route('users.index'), false);
    }

    public function test_an_admin_can_open_the_user_list(): void
    {
        $staff = $this->staff();

        $response = $this->actingAs($this->admin)->get(route('users.index'));

        $response->assertOk();
        $response->assertSee(e($this->admin->name));
        $response->assertSee(e($staff->name));
        $response->assertSee(e($staff->email));
        $response->assertSee('+ Add User', false);
    }

    public function test_staff_cannot_reach_the_user_pages(): void
    {
        $staff = $this->staff();

        $this->actingAs($staff)->get(route('users.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('users.create'))->assertForbidden();
        $this->actingAs($staff)->post(route('users.store'), $this->payload())->assertForbidden();
        $this->actingAs($staff)->get(route('users.edit', $this->admin))->assertForbidden();
        $this->actingAs($staff)->put(route('users.update', $this->admin), $this->payload())->assertForbidden();
    }

    public function test_a_guest_is_sent_to_the_login_page(): void
    {
        $this->get(route('users.index'))->assertRedirect(route('login'));
        $this->get(route('users.create'))->assertRedirect(route('login'));
    }

    public function test_an_admin_can_add_a_user(): void
    {
        $response = $this->actingAs($this->admin)->post(route('users.store'), $this->payload([
            'name' => 'MARIA SANTOS',
            'email' => 'maria@example.com',
            'role' => 'admin',
        ]));

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('users.index'));

        $created = User::where('email', 'maria@example.com')->firstOrFail();
        $this->assertSame('MARIA SANTOS', $created->name);
        $this->assertSame('admin', $created->role);
        $this->assertTrue($created->is_active);
        // The password must be stored hashed, never in the clear.
        $this->assertNotSame('secret123', $created->password);
        $this->assertTrue(Hash::check('secret123', $created->password));
    }

    public function test_a_new_user_can_sign_in(): void
    {
        $this->actingAs($this->admin)->post(route('users.store'), $this->payload());
        $this->post('/logout');

        $this->post('/login', ['email' => 'juan@example.com', 'password' => 'secret123']);

        $this->assertAuthenticatedAs(User::where('email', 'juan@example.com')->firstOrFail());
    }

    public function test_the_email_must_be_unique(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->actingAs($this->admin)
            ->post(route('users.store'), $this->payload(['email' => 'taken@example.com']))
            ->assertSessionHasErrors(['email']);

        $this->assertSame(1, User::where('email', 'taken@example.com')->count(), 'no duplicate may be created');
    }

    public function test_the_passwords_must_match(): void
    {
        $this->actingAs($this->admin)
            ->post(route('users.store'), $this->payload(['password_confirmation' => 'different']))
            ->assertSessionHasErrors(['password']);
    }

    public function test_a_weak_password_is_refused(): void
    {
        $this->actingAs($this->admin)
            ->post(route('users.store'), $this->payload([
                'password' => 'short',
                'password_confirmation' => 'short',
            ]))
            ->assertSessionHasErrors(['password']);
    }

    public function test_an_admin_can_edit_a_user(): void
    {
        $target = $this->staff();

        $response = $this->actingAs($this->admin)->put(route('users.update', $target), $this->payload([
            'name' => 'RENAMED',
            'email' => $target->email,
            'role' => 'admin',
        ]));

        $response->assertSessionHasNoErrors();
        $target->refresh();
        $this->assertSame('RENAMED', $target->name);
        $this->assertSame('admin', $target->role);
    }

    public function test_editing_leaves_the_password_alone_when_the_boxes_are_empty(): void
    {
        $target = $this->staff();
        $before = $target->password;

        $this->actingAs($this->admin)->put(route('users.update', $target), [
            'name' => 'SAME PASSWORD',
            'email' => $target->email,
            'role' => 'staff',
            'is_active' => '1',
            'password' => '',
            'password_confirmation' => '',
        ])->assertSessionHasNoErrors();

        $target->refresh();
        $this->assertSame('SAME PASSWORD', $target->name);
        $this->assertSame($before, $target->password, 'a blank field must not reset the password');
    }

    public function test_an_admin_can_reset_someone_s_password(): void
    {
        $target = $this->staff();

        $this->actingAs($this->admin)->put(route('users.update', $target), [
            'name' => $target->name,
            'email' => $target->email,
            'role' => 'staff',
            'is_active' => '1',
            'password' => 'brandnew1',
            'password_confirmation' => 'brandnew1',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('brandnew1', $target->fresh()->password));
    }

    public function test_an_admin_can_deactivate_a_user(): void
    {
        $target = $this->staff();

        $this->actingAs($this->admin)->put(route('users.update', $target), [
            'name' => $target->name,
            'email' => $target->email,
            'role' => 'staff',
            'is_active' => '0',
        ])->assertSessionHasNoErrors();

        $this->assertFalse($target->fresh()->is_active);
        // The row itself stays, so old records keep their author's name.
        $this->assertDatabaseHas('users', ['id' => $target->id]);
    }

    public function test_the_last_admin_cannot_be_deactivated(): void
    {
        $this->assertSame(1, User::where('role', 'admin')->where('is_active', true)->count(), 'precondition');

        $this->actingAs($this->admin)
            ->put(route('users.update', $this->admin), [
                'name' => $this->admin->name,
                'email' => $this->admin->email,
                'role' => 'admin',
                'is_active' => '0',
            ])
            ->assertForbidden();

        $this->assertTrue($this->admin->fresh()->is_active, 'the last admin must keep access');
    }

    public function test_an_admin_cannot_demote_themselves(): void
    {
        $this->actingAs($this->admin)
            ->put(route('users.update', $this->admin), [
                'name' => $this->admin->name,
                'email' => $this->admin->email,
                'role' => 'staff',
                'is_active' => '1',
            ])
            ->assertForbidden();

        $this->assertSame('admin', $this->admin->fresh()->role);
    }

    public function test_one_admin_can_demote_another(): void
    {
        $other = $this->otherAdmin();

        $this->actingAs($this->admin)->put(route('users.update', $other), [
            'name' => $other->name,
            'email' => $other->email,
            'role' => 'staff',
            'is_active' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertSame('staff', $other->fresh()->role);
    }

    public function test_users_cannot_be_deleted(): void
    {
        $target = $this->staff();

        // There is no delete route at all, so the only question is that the
        // policy refuses one if one is ever added.
        $this->assertFalse(Gate::forUser($this->admin)->allows('delete', $target));
        $this->assertDatabaseHas('users', ['id' => $target->id]);
    }

    public function test_a_deactivated_admin_is_turned_away(): void
    {
        $disabled = $this->makeUser('admin', active: false);

        // The role middleware logs a deactivated account out before the policy
        // is ever consulted, so the outcome is a bounce to the login page.
        $response = $this->actingAs($disabled)->get(route('users.index'));

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }
}
