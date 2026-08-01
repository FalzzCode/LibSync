<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class DiagnoseOAuth extends Command
{
    protected $signature = 'library:diagnose-oauth
                            {--verify-client : Verifikasi kecocokan Client ID dan Secret ke Google tanpa login akun}';

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

        if ($this->option('verify-client')) {
            $checks[] = $this->verifyGoogleClient();
        }

        $this->table(['Pemeriksaan', 'Status', 'Keterangan'], $checks);

        return collect($checks)->contains(fn (array $check) => $check[1] === 'GAGAL')
            ? self::FAILURE
            : self::SUCCESS;
    }

    /** @return array{string, string, string} */
    private function verifyGoogleClient(): array
    {
        if (! filled(config('services.google.client_id')) || ! filled(config('services.google.client_secret')) || ! filled(config('services.google.redirect'))) {
            return ['Verifikasi kredensial Google', 'GAGAL', 'Client ID, Secret, atau Redirect URI belum terisi'];
        }

        try {
            // An intentionally invalid code never accesses a user account.
            // Google validates the OAuth client first, so invalid_grant proves
            // this deployed Client ID and Secret are a matching pair.
            $response = Http::asForm()->timeout(12)->post('https://oauth2.googleapis.com/token', [
                'client_id' => config('services.google.client_id'),
                'client_secret' => config('services.google.client_secret'),
                'redirect_uri' => config('services.google.redirect'),
                'grant_type' => 'authorization_code',
                'code' => 'libsync-diagnostic-invalid-code',
            ]);
            $error = strtolower((string) $response->json('error'));

            if ($error === 'invalid_grant') {
                return ['Verifikasi kredensial Google', 'OK', 'Client ID dan Secret diterima Google'];
            }

            if (in_array($error, ['invalid_client', 'unauthorized_client'], true)) {
                return ['Verifikasi kredensial Google', 'GAGAL', 'Google menolak Client ID atau Client Secret'];
            }

            return ['Verifikasi kredensial Google', 'PERIKSA', 'Respons Google: '.($error !== '' ? $error : 'tidak diketahui')];
        } catch (\Throwable $exception) {
            return ['Verifikasi kredensial Google', 'PERIKSA', 'Tidak dapat menghubungi endpoint Google'];
        }
    }
}
