<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\SystemRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * Interactive, one-shot admin bootstrap — never a seeder, never hardcoded
 * credentials. The operator supplies real ones at the terminal.
 */
class MakeAdminCommand extends Command
{
    protected $signature = 'bp:make-admin';

    protected $description = 'Create the first super-admin user interactively.';

    public function handle(): int
    {
        $name = text(label: 'Name', required: true);

        $email = text(
            label: 'Email',
            required: true,
            validate: fn (string $value): ?string => Validator::make(
                ['email' => $value],
                ['email' => ['required', 'email', 'unique:users,email']]
            )->errors()->first('email'),
        );

        $plainPassword = password(
            label: 'Password',
            required: true,
            validate: fn (string $value): ?string => Validator::make(
                ['password' => $value],
                ['password' => ['required', 'string', 'min:8']]
            )->errors()->first('password'),
        );

        $user = new User;
        $user->forceFill([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($plainPassword),
            'system_role' => SystemRole::SUPER_ADMIN,
            'active' => true,
            'email_verified_at' => now(),
        ])->save();

        $this->info("Super admin created: {$user->email}");

        return self::SUCCESS;
    }
}
