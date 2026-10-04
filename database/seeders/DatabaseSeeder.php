<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    /**
     * Bootstrap accounts so a fresh installation has somebody who can sign in.
     *
     * These are throwaway credentials for local setup only. On a real machine,
     * sign in as the admin, add proper accounts from the Users page, then
     * delete these two. See DatabaseSeeder::guardProduction().
     */
    private const ACCOUNTS = [
        [
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'role' => 'admin',
        ],
        [
            'name' => 'Staff User',
            'email' => 'staff@example.com',
            'role' => 'staff',
        ],
    ];

    /**
     * The shared throwaway password. Deliberately weak: these accounts exist to
     * bootstrap a local install and are expected to be deleted afterwards.
     */
    private const PASSWORD = 'password';

    public function run(): void
    {
        $this->guardProduction();

        foreach (self::ACCOUNTS as $account) {
            // updateOrCreate rather than create: re-running `db:seed` must not
            // abort on the unique email constraint, which previously left a
            // half-seeded database. It also repairs any row whose password was
            // written in plain text by bypassing the model's `hashed` cast.
            User::updateOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    // Hash::make is explicit here even though the model casts
                    // `password` to `hashed`. updateOrCreate writes through the
                    // cast, but being explicit documents the intent and keeps
                    // the seeder correct if the cast is ever changed.
                    'password' => Hash::make(self::PASSWORD),
                    'role' => $account['role'],
                    'is_active' => true,
                ]
            );
        }

        $this->command?->info(sprintf(
            'Seeded %d bootstrap accounts (password: "%s"). Delete these once real accounts exist.',
            count(self::ACCOUNTS),
            self::PASSWORD
        ));
    }

    /**
     * Refuse to plant well-known passwords on a production environment.
     */
    private function guardProduction(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException(
                'DatabaseSeeder creates throwaway accounts with a known password and must not run in production. '
                .'Create real accounts through the Users page instead.'
            );
        }
    }
}