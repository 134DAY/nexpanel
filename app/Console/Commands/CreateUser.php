<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

/**
 * Provision a panel user from the CLI. Public web registration is disabled
 * (a server panel must not let anyone create a root-capable account), so this
 * is the supported way to add or reset an admin.
 *
 *   php artisan nexpanel:user admin@example.com --name=Admin
 */
class CreateUser extends Command
{
    protected $signature = 'nexpanel:user {email} {--name=Admin} {--password=}';

    protected $description = 'Create (or update the password of) a panel user';

    public function handle(): int
    {
        $email = strtolower(trim($this->argument('email')));
        $name  = (string) $this->option('name');
        $password = (string) ($this->option('password') ?: $this->secret('Password (min 8 chars)'));

        $v = Validator::make(
            ['email' => $email, 'name' => $name, 'password' => $password],
            ['email' => 'required|email', 'name' => 'required|string|max:255', 'password' => 'required|string|min:8']
        );
        if ($v->fails()) {
            foreach ($v->errors()->all() as $err) {
                $this->error($err);
            }

            return self::FAILURE;
        }

        $user = User::updateOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => Hash::make($password)]
        );

        $this->info(($user->wasRecentlyCreated ? 'Created' : 'Updated') . " user {$email}.");

        return self::SUCCESS;
    }
}
