<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#173d3a">
    <title>Masuk · LibSync</title>
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
    <link rel="stylesheet" href="{{ asset('css/responsive.css') }}">
    <link rel="stylesheet" href="{{ asset('css/dark-mode.css') }}?v=20260802-2">
    <link rel="stylesheet" href="{{ asset('css/branding.css') }}?v=20260808-2">
    <link rel="stylesheet" href="{{ asset('css/google-auth.css') }}?v=20260808-3">
</head>
<body class="auth-body">
    @php
        $localLoginEnabled = (bool) config('auth.local_login_enabled');
        $showLocalLogin = $localLoginEnabled && (request()->boolean('local') || $errors->any());
    @endphp

    <main class="login" id="mainContent">
        <section class="login__brand" aria-labelledby="loginBrandTitle">
            <div class="login__brand-inner">
                <div class="login__brand-head">
                    <span class="brand-logo"><img src="{{ asset('images/libsync-logo-512.png') }}" alt="Logo LibSync"></span>
                    <div><strong>LibSync</strong><small>Perpustakaan digital</small></div>
                </div>
                <p class="eyebrow login__brand-kicker">Ruang baca yang terhubung</p>
                <h1 id="loginBrandTitle">Ruang yang lebih baik untuk mengelola cerita.</h1>
                <p>Kelola koleksi, anggota, dan aktivitas perpustakaan dengan pengalaman yang sederhana dan teratur.</p>
                <div class="login__highlights" aria-label="Keunggulan LibSync">
                    <span><strong>01</strong> Koleksi rapi</span>
                    <span><strong>02</strong> Sirkulasi terpantau</span>
                </div>
                <div class="login__quote"><span aria-hidden="true">&ldquo;</span><p>Setiap buku adalah pintu menuju dunia baru.</p></div>
            </div>
        </section>

        <section class="login__form-wrapper" aria-labelledby="loginTitle">
            <form class="login__form" action="{{ route('login.attempt') }}" method="post">
                @csrf
                <div class="login__form-intro">
                    <p class="eyebrow">Selamat datang kembali</p>
                    <h2 id="loginTitle">Masuk ke akun Anda</h2>
                    <p>Gunakan akun Google Anda. Pada kunjungan pertama, LibSync akan membuat profil siswa secara otomatis.</p>
                </div>

                @error('email')
                    <div class="login__error" role="alert"><span aria-hidden="true">!</span><p>{{ $message }}</p></div>
                @enderror

                <a class="btn btn--google btn--block" href="{{ route('auth.google.redirect') }}">
                    <span class="google-mark" aria-hidden="true">G</span>
                    <span class="google-label">Lanjutkan dengan Google</span>
                    <span class="google-arrow" aria-hidden="true">&rarr;</span>
                </a>
                <p class="login__google-note"><strong>Tidak perlu NIS.</strong> Cukup pilih akun Google; data anggota akan dibuat atau dihubungkan memakai alamat email Google.</p>

                @if ($showLocalLogin)
                    <div class="login__local-fallback">
                        <div class="login__local-heading">
                            <div><strong>Login alternatif</strong><p>Gunakan sementara jika Google sedang tidak tersedia.</p></div>
                        </div>
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" autocomplete="username" placeholder="nama@sekolah.id" value="{{ old('email') }}" required autofocus @class(['is-invalid' => $errors->has('email')])>
                        </div>
                        <div class="form-group">
                            <label for="password">Password</label>
                            <input type="password" id="password" name="password" autocomplete="current-password" placeholder="Masukkan password" required>
                        </div>
                        <label class="check-field"><input type="checkbox" name="remember"><span>Ingat saya di perangkat ini</span></label>
                        <button type="submit" class="btn btn--primary btn--block">Masuk dengan password <span aria-hidden="true">&rarr;</span></button>
                    </div>
                @endif
            </form>
        </section>
    </main>
</body>
</html>
