<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#173d3a">
    <title>Masuk · LibSync</title>
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
    <link rel="stylesheet" href="{{ asset('css/responsive.css') }}">
    <link rel="stylesheet" href="{{ asset('css/google-auth.css') }}?v=20260808-1">
    <link rel="stylesheet" href="{{ asset('css/dark-mode.css') }}">
    <link rel="stylesheet" href="{{ asset('css/branding.css') }}">
</head>
<body class="auth-body">
    <main class="login" id="mainContent">
        <section class="login__brand"><div class="login__brand-inner"><span class="brand-logo"><img src="{{ asset('images/libsync-logo-512.png') }}" alt="Logo LibSync"></span><p class="eyebrow brand-eyebrow">LibSync</p><h1>Ruang yang lebih baik untuk mengelola cerita.</h1><p>Kelola koleksi, anggota, dan aktivitas perpustakaan dengan pengalaman yang sederhana dan teratur.</p><div class="login__quote"><span>“</span><p>Setiap buku adalah pintu menuju dunia baru.</p></div></div></section>
        <section class="login__form-wrapper">
            <form class="login__form" action="{{ route('login.attempt') }}" method="post">
                @csrf
                <div class="login__form-intro"><span class="brand-logo brand-logo--mobile"><img src="{{ asset('images/libsync-logo-512.png') }}" alt="Logo LibSync"></span><p class="eyebrow">Selamat datang kembali</p><h2>Masuk ke akun Anda</h2><p>Masuk menggunakan akun Google yang sudah terhubung ke LibSync.</p></div>
                @error('email')<small class="field-error">{{ $message }}</small>@enderror
                <a class="btn btn--google btn--block" href="{{ route('auth.google.redirect') }}"><span aria-hidden="true">G</span>Lanjutkan dengan Google</a>
                <p class="login__activation-link">Siswa baru atau belum terhubung? <a href="{{ route('student.activation.create') }}">Aktifkan akun dengan NIS →</a></p>
                @if (app()->environment('local'))
                    <p class="login__divider"><span>atau akun lokal untuk pengujian</span></p>
                    <div class="form-group"><label for="email">Email</label><input type="email" id="email" name="email" placeholder="nama@sekolah.id" value="{{ old('email') }}" required autofocus @class(['is-invalid' => $errors->has('email')])></div>
                    <div class="form-group"><label for="password">Password</label><input type="password" id="password" name="password" placeholder="Masukkan password" required></div>
                    <label class="check-field"><input type="checkbox" name="remember"><span>Ingat saya di perangkat ini</span></label>
                    <button type="submit" class="btn btn--primary btn--block">Masuk dengan password →</button>
                @endif
            </form>
        </section>
    </main>
</body>
</html>
