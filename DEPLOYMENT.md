# Deploy LibSync

Dokumen ini untuk server production. Jangan salin file .env lokal karena berisi konfigurasi development dan kredensial lokal.

## 1. Siapkan environment

1. Salin .env.production.example menjadi .env.
2. Isi APP_KEY melalui perintah php artisan key:generate.
3. Isi URL HTTPS final, database production, dan kredensial Google OAuth.
4. Pastikan APP_ENV=production, APP_DEBUG=false, dan SESSION_SECURE_COOKIE=true.
5. Di Google Cloud, tambahkan URL callback dari nilai GOOGLE_REDIRECT_URI sebagai Authorized redirect URI.

## 2. Instalasi aplikasi

Jalankan dari folder project:

    composer install --no-dev --optimize-autoloader
    npm ci
    npm run build
    php artisan migrate --force
    php artisan storage:link
    php artisan optimize

Buat admin pertama secara interaktif. Jangan menaruh password di history terminal:

    php artisan library:create-admin --name="Nama Admin" --email="admin@sekolah.sch.id"

Seeder production hanya menyiapkan aturan sistem; akun demo tidak dibuat.

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
- Tidak ada akun demo atau password default.
- Web server hanya mengekspos folder public.
- storage dan bootstrap/cache dapat ditulis oleh proses web server.
