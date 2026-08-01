<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateDeveloperAccount extends Command
{
    protected $signature = 'library:create-developer
                            {--name=Developer : Nama akun developer}
                            {--email=developer@libsync.test : Email akun developer}
                            {--password= : Password minimal 12 karakter; kosongkan agar diminta tersembunyi}';

    protected $description = 'Membuat akun developer lokal untuk menguji role tanpa memberi akses developer kepada admin.';

    public function handle(): int
    {
        if (! app()->environment('local')) {
            $this->error('Akun developer hanya boleh dibuat pada environment local.');

            return self::FAILURE;
        }

        $name = trim((string) $this->option('name'));
        $email = trim((string) $this->option('email'));
        $password = (string) ($this->option('password') ?: $this->secret('Password (minimal 12 karakter)'));

        if ($name === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($password) < 12) {
            $this->error('Nama wajib diisi, email harus valid, dan password minimal 12 karakter.');

            return self::FAILURE;
        }

        if (User::where('email', $email)->exists()) {
            $this->error('Email tersebut sudah digunakan.');

            return self::FAILURE;
        }

        User::create(['name' => $name, 'email' => $email, 'password' => Hash::make($password), 'role' => 'developer']);
        $this->info("Akun developer {$email} berhasil dibuat.");

        return self::SUCCESS;
    }
}
