<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class MakeUserAdmin extends Command
{
    protected $signature = 'user:make-admin {email : The email of the user to promote}';

    protected $description = 'Promote a user to admin by their email address';

    public function handle(): int
    {
        $email = $this->argument('email');

        $user = User::where('email', $email)->first();

        if (!$user) {
            $this->error("No user found with email: {$email}");
            return self::FAILURE;
        }

        $user->update(['is_admin' => true]);

        $this->info("✓ {$user->full_name} ({$email}) is now an admin.");

        return self::SUCCESS;
    }
}
