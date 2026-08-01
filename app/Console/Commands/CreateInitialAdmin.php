<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateInitialAdmin extends Command
{
    protected $signature = 'library:create-admin
                            {--name= : Nama lengkap administrator}
                            {--email= : Email administrator}
                            {--password= : Password minimal 12 karakter; kosongkan agar diminta tersembunyi}';

    protected $description = 'Membuat akun administrator pertama untuk instalasi production.';

    public function handle(): int
    {
        $name = trim((string) ($this->option('name') ?: $this->ask('Nama administrator')));
        $email = trim((string) ($this->option('email') ?: $this->ask('Email administrator')));
        $password = (string) ($this->option('password') ?: $this->secret('Password (minimal 12 karakter)'));

        if ($name === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($password) < 12) {
            $this->error('Nama wajib diisi, email harus valid, dan password minimal 12 karakter.');

            return self::FAILURE;
        }
        if (User::where('email', $email)->exists()) {
            $this->error('Email tersebut sudah digunakan.');

            return self::FAILURE;
        }

        User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'role' => 'admin',
        ]);

        $this->info("Admin {$email} berhasil dibuat.");

        return self::SUCCESS;
    }
}
