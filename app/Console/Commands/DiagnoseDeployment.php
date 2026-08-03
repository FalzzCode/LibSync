<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DiagnoseDeployment extends Command
{
    protected $signature = 'library:deploy-check';

    protected $description = 'Memeriksa kesiapan konfigurasi LibSync sebelum dipasang di domain produksi.';

    /**
     * The command deliberately reports missing secrets without ever printing
     * their values. It can be run locally to see what still needs to be set,
     * then run again on the production server as a go-live gate.
     */
    public function handle(): int
    {
        $production = app()->environment('production');
        $appUrl = rtrim((string) config('app.url'), '/');
        $appHost = (string) parse_url($appUrl, PHP_URL_HOST);
        $appUrlIsHttps = str_starts_with($appUrl, 'https://');
        $placeholderUrl = $this->isPlaceholderHost($appHost);

        $checks = [
            ['APP_ENV produksi', $production ? 'OK' : 'PERIKSA', (string) config('app.env')],
            ['APP_DEBUG nonaktif', config('app.debug') === false ? 'OK' : ($production ? 'GAGAL' : 'PERIKSA'), config('app.debug') ? 'true' : 'false'],
            ['APP_KEY terisi', $this->hasValidAppKey() ? 'OK' : 'GAGAL', $this->hasValidAppKey() ? 'Nilai tersimpan' : 'Belum diisi atau tidak valid'],
            ['APP_URL', $placeholderUrl ? ($production ? 'GAGAL' : 'PERIKSA') : 'OK', $appUrl !== '' ? $appUrl : 'Belum diisi'],
            ['APP_URL HTTPS', $appUrlIsHttps ? 'OK' : ($production ? 'GAGAL' : 'PERIKSA'), $appUrlIsHttps ? 'Aktif' : 'Gunakan https:// pada produksi'],
            ['Login lokal', config('auth.local_login_enabled') === false ? 'OK' : ($production ? 'GAGAL' : 'PERIKSA'), config('auth.local_login_enabled') ? 'Aktif' : 'Nonaktif'],
            ['Google Client ID', filled(config('services.google.client_id')) ? 'OK' : ($production ? 'GAGAL' : 'PERIKSA'), 'Nilai disembunyikan'],
            ['Google Client Secret', filled(config('services.google.client_secret')) ? 'OK' : ($production ? 'GAGAL' : 'PERIKSA'), 'Nilai disembunyikan'],
            ['Google Redirect URI', $this->redirectIsReady($appUrl, (string) config('services.google.redirect'), $production) ? 'OK' : ($production ? 'GAGAL' : 'PERIKSA'), (string) config('services.google.redirect')],
            ['Session database', config('session.driver') === 'database' ? 'OK' : ($production ? 'GAGAL' : 'PERIKSA'), (string) config('session.driver')],
            ['Cookie session HTTPS', config('session.secure') ? 'OK' : ($production ? 'GAGAL' : 'PERIKSA'), config('session.secure') ? 'Aktif' : 'Nonaktif'],
            ['Asset build', is_file(public_path('build/manifest.json')) ? 'OK' : ($production ? 'GAGAL' : 'PERIKSA'), 'public/build/manifest.json'],
            ['Folder storage/cache', $this->writableDirectoriesReady() ? 'OK' : 'GAGAL', 'storage dan bootstrap/cache'],
        ];

        try {
            DB::connection()->getPdo();
            $requiredTables = ['pengguna', 'anggota', 'buku', 'peminjaman', 'sessions'];
            $missingTables = array_values(array_filter($requiredTables, static fn (string $table): bool => ! Schema::hasTable($table)));
            $checks[] = ['Koneksi database', 'OK', DB::connection()->getDatabaseName()];
            $checks[] = ['Tabel inti', $missingTables === [] ? 'OK' : 'GAGAL', $missingTables === [] ? 'Semua tersedia' : 'Kurang: '.implode(', ', $missingTables)];
        } catch (\Throwable $exception) {
            $checks[] = ['Koneksi database', 'GAGAL', 'Tidak dapat terhubung'];
            $checks[] = ['Tabel inti', 'GAGAL', 'Periksa DB_HOST, DB_DATABASE, DB_USERNAME, dan DB_PASSWORD'];
        }

        $this->table(['Pemeriksaan', 'Status', 'Keterangan'], $checks);

        if (collect($checks)->contains(fn (array $check): bool => $check[1] === 'GAGAL')) {
            $this->newLine();
            $this->error('Belum siap produksi. Isi pemeriksaan berstatus GAGAL lalu jalankan perintah ini lagi.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Konfigurasi wajib tidak memiliki kegagalan. Lanjutkan checklist DNS, HTTPS, backup, dan cron.');

        return self::SUCCESS;
    }

    private function hasValidAppKey(): bool
    {
        $key = (string) config('app.key');

        if (str_starts_with($key, 'base64:')) {
            return strlen((string) base64_decode(substr($key, 7), true)) === 32;
        }

        return $key !== '';
    }

    private function isPlaceholderHost(string $host): bool
    {
        return $host === ''
            || in_array($host, ['localhost', '127.0.0.1', '::1', 'perpustakaan.sekolah.sch.id'], true)
            || str_contains($host, 'example.');
    }

    private function redirectIsReady(string $appUrl, string $redirect, bool $requireHttps): bool
    {
        if ($redirect === '' || ! str_ends_with($redirect, '/auth/google/callback')) {
            return false;
        }

        $appHost = (string) parse_url($appUrl, PHP_URL_HOST);
        $redirectHost = (string) parse_url($redirect, PHP_URL_HOST);

        return $appHost !== ''
            && $appHost === $redirectHost
            && (! $requireHttps || str_starts_with($redirect, 'https://'));
    }

    private function writableDirectoriesReady(): bool
    {
        $directories = [
            storage_path(),
            storage_path('app/private'),
            storage_path('app/public'),
            storage_path('framework/cache'),
            storage_path('framework/sessions'),
            storage_path('framework/views'),
            base_path('bootstrap/cache'),
        ];

        foreach ($directories as $directory) {
            if (! is_dir($directory) || ! is_writable($directory)) {
                return false;
            }
        }

        return true;
    }
}
