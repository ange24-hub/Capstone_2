<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateMunicipalAdmin extends Command
{
    protected $signature = 'sbispgm:create-admin';

    protected $description = 'Create a municipal administrator using a privately entered password';

    public function handle(): int
    {
        $data = [
            'name' => trim((string) $this->ask('Administrator name')),
            'email' => strtolower(trim((string) $this->ask('Administrator email'))),
            'password' => $this->secret('Password (at least 12 characters, letters and numbers)'),
            'password_confirmation' => $this->secret('Confirm password'),
        ];
        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(12)->letters()->numbers()],
        ]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }
            return self::FAILURE;
        }

        User::create([
            'name' => $data['name'], 'email' => $data['email'], 'password' => $data['password'],
            'role' => User::ROLE_MUNICIPAL_LGU, 'approval_status' => User::APPROVAL_APPROVED,
            'approved_at' => now(),
        ]);
        $this->info('Municipal administrator created. Keep these credentials private.');
        return self::SUCCESS;
    }
}
