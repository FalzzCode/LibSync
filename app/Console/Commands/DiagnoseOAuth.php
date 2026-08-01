<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DiagnoseOAuth extends Command
{
    protected $signature = 'library:diagnose-oauth';

    protected $description = 'Memeriksa konfigurasi Google OAuth, database, dan session tanpa menampilkan secret.';

    public function handle(): int
    {
        $isProduction = app()->environment('production');
        $appUrl = (string) config('app.url');
        $appUrlIsHttps = str_starts_with($appUrl, 'https://');

        $checks = [
            ['Environment', $isProduction ? 'OK' : 'PERIKSA', app()->environment()],
            ['APP_URL HTTPS', $appUrlIsHttps ? 'OK' : ($isProduction ? 'GAGAL' : 'PERIKSA'), $appUrl],
            ['Google Client ID', filled(config('services.google.client_id')) ? 'OK' : 'GAGAL', 'Nilai disembunyikan'],
            ['Google Client Secret', filled(config('services.google.client_secret')) ? 'OK' : 'GAGAL', 'Nilai disembunyikan'],
            ['Google Redirect URI', filled(config('services.google.redirect')) ? 'OK' : 'GAGAL', (string) config('services.google.redirect')],
            ['Session driver', config('session.driver') === 'database' ? 'OK' : 'PERIKSA', (string) config('session.driver')],
            ['Session cookie HTTPS', config('session.secure') ? 'OK' : 'PERIKSA', config('session.secure') ? 'Aktif' : 'Nonaktif'],
            ['Log channel', in_array('stderr', config('logging.channels.stack.channels', []), true) ? 'OK' : 'PERIKSA', implode(',', config('logging.channels.stack.channels', []))],
        ];

        try {
            DB::connection()->getPdo();
            $checks[] = ['Koneksi database', 'OK', DB::connection()->getDatabaseName()];
            $checks[] = ['Kolom OAuth users', Schema::hasColumns('users', ['email', 'google_id', 'avatar_url']) ? 'OK' : 'GAGAL', 'email, google_id, avatar_url'];
            $checks[] = ['Tabel sessions', Schema::hasTable('sessions') ? 'OK' : 'GAGAL', 'sessions'];
        } catch (\Throwable $exception) {
            $checks[] = ['Koneksi database', 'GAGAL', $exception->getMessage()];
        }

        $this->table(['Pemeriksaan', 'Status', 'Keterangan'], $checks);

        return collect($checks)->contains(fn (array $check) => $check[1] === 'GAGAL')
            ? self::FAILURE
            : self::SUCCESS;
    }
}
