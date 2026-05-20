<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class MakeAdmin extends Command
{
    protected $signature   = 'admin:seed';
    protected $description = 'Promote ADMIN_EMAIL env var user to admin';

    public function handle()
    {
        $email = env('ADMIN_EMAIL');

        if (!$email) {
            $this->info('ADMIN_EMAIL not set — skipping admin seed.');
            return 0;
        }

        $user = User::where('email', $email)->first();

        if (!$user) {
            $this->warn("No user found with email: {$email}");
            return 0;
        }

        $user->update(['is_admin' => true, 'is_active' => true]);
        $this->info("Admin granted to: {$email}");

        return 0;
    }
}
