<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\SystemRolesSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * Interactive, one-shot admin bootstrap — never a seeder, never hardcoded
 * credentials. The operator supplies real ones at the terminal. Name and email
 * can be passed as options to skip their prompts; the password is always
 * prompted, so it never lands in shell history.
 *
 * By default the account is a SUPER ADMIN: the first account should be one,
 * because only a super admin can grant super admin and untangle any role
 * (an Administrator acts only on users and roles whose permissions it holds),
 * so an install whose only admin is on a role could never fix a role mistake. Every admin
 * after the first should be an Administrator instead (`--administrator`): the
 * seeded role that grants everything a role can, without the master key.
 */
class MakeAdminCommand extends Command
{
    protected $signature = 'bp:make-admin
                            {--first-name= : Skip the first name prompt}
                            {--last-name= : Skip the last name prompt}
                            {--email= : Skip the email prompt}
                            {--administrator : Put the user on the seeded Administrator role instead of the super-admin flag}';

    protected $description = 'Create an admin user interactively — a super admin, or with --administrator the seeded Administrator role.';

    public function handle(): int
    {
        $asAdministrator = (bool) $this->option('administrator');
        $role = null;

        if ($asAdministrator) {
            $role = Role::systemRoles()->where('name', SystemRolesSeeder::ADMINISTRATOR)->first();

            if ($role === null) {
                $this->error(sprintf(
                    "No '%s' system role exists to assign. Seed it first (php artisan db:seed --class=SystemRolesSeeder) or create it on the Roles screen.",
                    SystemRolesSeeder::ADMINISTRATOR,
                ));

                return self::FAILURE;
            }
        } elseif (User::query()->where('is_super_admin', true)->exists()) {
            $this->warn('A super admin already exists. Routine admins should be Administrators, not super admins — consider --administrator.');
        }

        // Validated in its normalized form so the uniqueness check matches the
        // stored value, whatever casing or padding the operator typed.
        $emailError = fn (string $value): ?string => Validator::make(
            ['email' => User::normalizeEmail($value)],
            ['email' => ['required', 'email', 'unique:users,email']]
        )->errors()->first('email') ?: null;

        // Options are checked before any prompt, so a bad one fails before the operator types anything.
        [$firstName, $lastName, $email] = collect(['first-name', 'last-name', 'email'])
            ->map(fn (string $option): string => trim((string) $this->option($option)))
            ->all();

        if ($email !== '' && ($error = $emailError($email)) !== null) {
            $this->error($error);

            return self::FAILURE;
        }

        $firstName = $firstName !== '' ? $firstName : text(label: 'First name', required: true);

        $lastName = $lastName !== '' ? $lastName : text(label: 'Last name', required: true);

        $email = User::normalizeEmail($email !== '' ? $email : text(label: 'Email', required: true, validate: $emailError));

        $plainPassword = password(
            label: 'Password',
            required: true,
            validate: fn (string $value): ?string => Validator::make(
                ['password' => $value],
                ['password' => ['required', 'string', Password::default()]]
            )->errors()->first('password'),
        );

        $user = new User;
        $user->forceFill([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'password' => Hash::make($plainPassword),
            'is_super_admin' => ! $asAdministrator,
            'active' => true,
            'email_verified_at' => now(),
        ])->save();

        if ($role !== null) {
            $user->assignRole($role);
            $this->info("Administrator created: {$user->email}");
        } else {
            $this->info("Super admin created: {$user->email}");
        }

        return self::SUCCESS;
    }
}
