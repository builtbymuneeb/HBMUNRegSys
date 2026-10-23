<?php

namespace App\Console\Commands;

use App\Concerns\PasswordValidationRules;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

#[Signature('admin:create {--name= : The admin\'s full name} {--email= : The admin\'s login email}')]
#[Description('Create an admin account, or promote an existing account to admin')]
class CreateAdmin extends Command
{
    use PasswordValidationRules;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = $this->stringOption('email') ?? text(
            label: 'Email',
            required: true,
            validate: fn (string $value) => Validator::make(['email' => $value], ['email' => ['email', 'max:255']])->errors()->first('email') ?: null,
        );

        $existing = User::where('email', $email)->first();

        if ($existing) {
            if ($existing->isAdmin()) {
                $this->components->info("{$email} is already an admin.");

                return self::SUCCESS;
            }

            if (! confirm("{$email} already has an account ({$existing->role->label()}). Make it an admin?", default: false)) {
                return self::FAILURE;
            }

            $existing->forceFill(['role' => UserRole::Admin, 'requested_role' => null])->save();
            AuditLog::record('promoted_to_admin', $existing, properties: ['via' => 'console']);
            $this->components->info("{$email} is now an admin.");

            return self::SUCCESS;
        }

        $name = $this->stringOption('name') ?? text(label: 'Full name', required: true);

        $password = password(label: 'Password', required: true, hint: 'At least 8 characters with upper and lower case letters and a number.');
        $confirmation = password(label: 'Confirm password', required: true);

        $validator = Validator::make(
            ['name' => $name, 'password' => $password, 'password_confirmation' => $confirmation],
            ['name' => ['required', 'string', 'max:255'], 'password' => $this->passwordRules()],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $admin = new User(['name' => $name, 'email' => $email, 'password' => $password]);
        $admin->forceFill(['role' => UserRole::Admin, 'email_verified_at' => now()])->save();
        AuditLog::record('admin_created', $admin, properties: ['via' => 'console']);

        $this->components->info("Admin account created for {$email}. Log in at ".route('login'));

        return self::SUCCESS;
    }

    /**
     * Read a text option, treating a missing or empty value as not given.
     */
    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
