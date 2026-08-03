# Deploy LibSync

Dokumen ini untuk server production. Jangan salin file .env lokal karena berisi konfigurasi development dan kredensial lokal.

LibSync membutuhkan hosting Laravel/PHP biasa (VPS, shared hosting/cPanel, atau
server sekolah) dengan PHP 8.2+, MySQL, Composer, dan cron. Arahkan document
root web server ke folder `public`, bukan ke root project. LibSync tidak
bergantung pada Railway, Vercel, atau runtime serverless.

## 1. Siapkan environment

1. Salin .env.production.example menjadi .env.
2. Jika `APP_KEY` masih kosong, isi sekali dengan `php artisan key:generate --force`.
   Jangan menjalankan perintah ini pada deployment berikutnya karena akan
   membuat sesi/cookie lama dan data terenkripsi tidak dapat dibaca.
3. Isi `APP_URL` dengan domain sekolah final, misalnya `https://perpustakaan.sekolah.sch.id`. Cover buku, foto profil, manifest, dan CSS otomatis mengikuti URL ini. `ASSET_URL` hanya perlu diisi jika static files memakai CDN/host terpisah.
4. Pastikan APP_ENV=production, APP_DEBUG=false, SESSION_COOKIE=libsync-session-v2, SESSION_SECURE_COOKIE=true, dan LOG_STACK=stderr agar error production terlihat di log provider. Perubahan nama cookie akan mengeluarkan sesi browser lama satu kali.
5. Isi kredensial Google OAuth. `GOOGLE_REDIRECT_URI` otomatis mengikuti `APP_URL`; jika ingin memakai host autentikasi terpisah, isi nilainya secara eksplisit.
6. Jika semua akun memakai email sekolah, isi `GOOGLE_ALLOWED_DOMAIN` (contoh `sekolah.sch.id`).
7. Di Google Cloud, tambahkan URL callback dari nilai `GOOGLE_REDIRECT_URI` sebagai Authorized redirect URI.

8. Setelah `.env` diisi, jalankan preflight berikut dari folder project:

       php artisan library:deploy-check
       php artisan library:diagnose-oauth --verify-client

   Perintah pertama memeriksa environment, key, HTTPS, database, session,
   asset build, dan permission folder tanpa mencetak secret. Perintah kedua
   memeriksa pasangan kredensial Google ke endpoint Google.

Foto profil disimpan di `storage/app/private/profile-photos` dan hanya disajikan
melalui route yang terautentikasi. Pada instalasi lama, pindahkan isi
`storage/app/public/profile-photos` ke folder privat tersebut setelah menyalin
backup; nama path di database tetap sama. Jangan biarkan folder
`public/profile-photos` atau symlink `storage/profile-photos` berisi foto lama.

Secara default, Google login membuat profil `student` dan data anggota baru
tanpa NIS. Pertahankan `GOOGLE_AUTO_REGISTER_STUDENTS=true` untuk sekolah tanpa
NIS. Set ke `false` hanya bila petugas ingin membuat data anggota lebih dulu.

### Saat domain sekolah sudah siap

Di provider hosting, ubah hanya variabel berikut lalu lakukan redeploy:

```dotenv
APP_URL=https://perpustakaan.sekolah.sch.id
```

`ASSET_URL` dan `GOOGLE_REDIRECT_URI` boleh diisi dengan URL HTTPS yang sama jika
provider hosting tidak mendukung referensi variabel. Jika dibiarkan kosong/tidak
dibuat, aplikasi menurunkan keduanya dari `APP_URL`.

Tambahkan domain yang sama ke DNS hosting dan tunggu sertifikat HTTPS aktif. Jangan mengubah URL secara manual di Blade atau controller.

Untuk subdomain, buat record `A` (atau `CNAME` ke host deployment) seperti
`perpustakaan.sekolah.sch.id`, arahkan document root ke folder `public`, lalu
isi `APP_URL` dengan subdomain tersebut. Domain utama dan subdomain sama-sama
didukung; yang penting URL pada `APP_URL` dan callback Google harus identik,
termasuk `https://` dan path `/auth/google/callback`.

## 2. Instalasi aplikasi

Jalankan dari folder project:

    composer install --no-dev --optimize-autoloader
    npm ci
    npm run build
    php artisan migrate --seed --force
    php artisan storage:link
    php artisan optimize

Migrasi akan memeriksa duplikat kode buku, identitas anggota (email/NIS/NISN),
dan nama kategori sebelum membuat constraint unik. Jika ditemukan, migrasi
berhenti dengan pesan nilai yang perlu dirapikan; data tidak dihapus otomatis.
Migrasi optimasi indeks hanya menghapus indeks yang benar-benar redundant; isi
tabel dan constraint unik tetap dipertahankan.

Jika hosting tidak menyediakan Node.js, jalankan `npm ci` dan `npm run build`
di komputer/CI lalu unggah folder `public/build` bersama project. Web server
tetap harus menunjuk ke folder `public`.

Nilai berikut memang harus diisi dari akun/domain produksi dan tidak aman untuk
diisi otomatis oleh source code:

- `APP_URL` dan `GOOGLE_REDIRECT_URI`: domain HTTPS final.
- `DB_DATABASE`, `DB_USERNAME`, dan `DB_PASSWORD`: database produksi yang dibuat
  di provider hosting.
- `GOOGLE_CLIENT_ID` dan `GOOGLE_CLIENT_SECRET`: kredensial OAuth dari Google
  Cloud. Secret jangan pernah di-commit ke GitHub.

Buat admin pertama secara interaktif. Jangan menaruh password di history terminal:

    php artisan library:create-admin --name="Nama Admin" --email="admin@sekolah.sch.id"

Seeder production hanya menyiapkan aturan sistem; akun demo tidak dibuat. Jika
migrasi sudah pernah dijalankan tanpa `--seed`, jalankan `php artisan db:seed --force`
sekali sebelum membuat admin pertama.

## 3. Scheduler dan queue

Scheduler harus berjalan setiap menit agar peringatan terlambat serta masa reservasi diproses.

Linux cron:

    * * * * * cd /var/www/libsync && php artisan schedule:run >> /dev/null 2>&1

Windows Task Scheduler: buat task setiap 1 menit dengan Program C:\xampp\php\php.exe dan Arguments artisan schedule:run, lalu set folder project sebagai Start in.

Jika notifikasi atau queue kelak diganti dari database ke driver asynchronous, jalankan juga worker queue sebagai service.

## 4. Checklist go-live

- Database dibackup otomatis dan pemulihan pernah diuji.
- HTTPS aktif dan sertifikat valid.
- Redirect URI Google sama persis dengan GOOGLE_REDIRECT_URI.
- Setelah env production terisi, jalankan `php artisan library:diagnose-oauth`
  (tambahkan `--verify-client` bila server boleh menghubungi Google) dan pastikan
  tidak ada pemeriksaan berstatus GAGAL.
- Jalankan `php artisan library:deploy-check`; tidak boleh ada pemeriksaan
  berstatus GAGAL sebelum DNS diarahkan.
- Tidak ada akun demo atau password default.
- Web server hanya mengekspos folder public.
- storage dan bootstrap/cache dapat ditulis oleh proses web server.
- Endpoint health `GET /up` mengembalikan status 200 setelah deploy.
- Jalankan `composer validate --strict`, `php artisan test`, dan `npm run build`
  di CI sebelum perubahan dipromosikan.
