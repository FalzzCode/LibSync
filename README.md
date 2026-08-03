# LibSync

Sistem perpustakaan sekolah berbasis Laravel: katalog, anggota, sirkulasi, denda, daftar tunggu, inventaris fisik, portal siswa, dan login Google dengan pembuatan profil siswa otomatis.

## Menjalankan lokal

1. Salin `.env.example` menjadi `.env`, lalu sesuaikan koneksi MySQL Laragon.
2. Jalankan `composer install`, `php artisan key:generate`, dan `php artisan migrate --seed`.
3. Jalankan `npm install` lalu `npm run build`.
4. Jalankan `php artisan serve`, lalu buka `http://127.0.0.1:8000`.
5. Untuk menjalankan pengecekan keterlambatan dan masa reservasi otomatis saat pengembangan, buka terminal kedua dan jalankan `php artisan schedule:work`.

Login email/password lokal dikendalikan oleh `LOCAL_LOGIN_ENABLED=true`. Jika form tidak
muncul setelah mengubah `.env`, jalankan `php artisan optimize:clear` lalu restart server.

Akun demo:

- Admin: admin@perpustakaan.test / password123
- Petugas: staff@perpustakaan.test / password123

## Aktifkan login Google

1. Buat project di Google Cloud Console, lalu konfigurasi OAuth consent screen.
2. Buat OAuth Client ID bertipe Web application.
3. Tambahkan URI redirect pada Google Cloud: `http://127.0.0.1:8000/auth/google/callback`.
4. Isi `.env` dengan `APP_URL=http://127.0.0.1:8000`, `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, dan `GOOGLE_REDIRECT_URI`.
5. Jalankan `php artisan config:clear`, lalu restart server Laravel.

Login Google baru membuat profil berperan `student` secara otomatis, tanpa NIS atau kode aktivasi. Akun admin/staff tetap harus dibuat petugas dan tidak pernah dibuat otomatis. Untuk membatasi akses hanya ke Google Workspace sekolah, isi `GOOGLE_ALLOWED_DOMAIN`; jika dibiarkan kosong, semua akun Google dapat membuat profil siswa.

## Checklist deployment

- Atur `APP_ENV=production` dan `APP_DEBUG=false`.
- Isi `APP_URL` dengan domain HTTPS sekolah. `ASSET_URL` dan `GOOGLE_REDIRECT_URI` akan mengikuti `APP_URL` jika tidak diisi secara eksplisit.
- Tambahkan `https://domain-sekolah/auth/google/callback` ke Authorized redirect URI Google.
- Jalankan `php artisan optimize` setelah konfigurasi final.
- Jalankan `php artisan library:deploy-check` untuk memeriksa env, database,
  session, permission folder, dan asset build sebelum DNS diarahkan.
- Jalankan `php artisan library:diagnose-oauth --verify-client` setelah server
  boleh menghubungi Google.
- Jadwalkan `php artisan schedule:run` setiap menit melalui cron/Task Scheduler, atau jalankan `php artisan schedule:work` sebagai service. Tanpa ini, peringatan keterlambatan dan batas waktu reservasi tidak diproses otomatis.
- Gunakan database MySQL terkelola/ter-backup dan simpan snapshot admin di lokasi aman.

Setiap push atau pull request menjalankan verifikasi otomatis di GitHub Actions:
validasi Composer, install dependency, build Vite, cache Blade, seluruh test, dan
pemeriksaan whitespace. Workflow ini menjadi gerbang sebelum kode dipromosikan ke
hosting.

## Operasional tambahan

- **Eksemplar buku**: buat kode inventaris serta barcode per salinan fisik.
  Angka `stok` pada buku tetap menjadi jumlah yang dipakai alur sirkulasi;
  eksemplar menyimpan metadata kondisi/lokasi dan dapat dikembangkan ke scan
  per salinan tanpa mengubah riwayat transaksi.
- **Daftar tunggu**: siswa dapat menunggu buku saat stok kosong.
- **Perpanjangan**: siswa boleh mengajukan satu kali; petugas menyetujuinya.
- **Denda**: denda keterlambatan dibuat otomatis saat pengembalian dan pelunasan dicatat oleh petugas.
- **Laporan**: ekspor CSV tersedia dari menu Ekspor laporan dan Denda.
- **PWA**: aplikasi dapat dipasang dari browser pada perangkat yang mendukung PWA.
- **Backup**: admin dapat mengunduh snapshot JSON dari menu Backup data. Simpan berkas ini di penyimpanan sekolah yang aman; snapshot tidak menyertakan password pengguna.
