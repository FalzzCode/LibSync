<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#173d3a">
    <title>Aktivasi akun siswa · LibSync</title>
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
    <link rel="stylesheet" href="{{ asset('css/responsive.css') }}">
    <link rel="stylesheet" href="{{ asset('css/google-auth.css') }}?v=20260808-1">
    <link rel="stylesheet" href="{{ asset('css/branding.css') }}">
</head>
<body class="auth-body">
    <main class="login">
        <section class="login__brand"><div class="login__brand-inner"><span class="brand-logo"><img src="{{ asset('images/libsync-logo-512.png') }}" alt="Logo LibSync"></span><p class="eyebrow brand-eyebrow">LibSync</p><h1>Aktifkan akses perpustakaanmu.</h1><p>Hubungkan akun Google ke data siswa sekolah satu kali. Login berikutnya cukup menggunakan Google.</p></div></section>
        <section class="login__form-wrapper">
            <form class="login__form" method="POST" action="{{ route('student.activation.store') }}">
                @csrf
                <div class="login__form-intro"><p class="eyebrow">Aktivasi siswa</p><h2>Hubungkan akun Google</h2><p>Masukkan NIS dan kode aktivasi yang diberikan petugas perpustakaan.</p></div>
                <div class="form-group"><label for="nis">NIS</label><input id="nis" name="nis" value="{{ old('nis') }}" required autofocus @class(['is-invalid' => $errors->has('nis')])>@error('nis')<small class="field-error">{{ $message }}</small>@enderror</div>
                <div class="form-group"><label for="activation_code">Kode aktivasi</label><input id="activation_code" name="activation_code" autocomplete="one-time-code" placeholder="LIB-XXXXXXXX" required @class(['is-invalid' => $errors->has('activation_code')])>@error('activation_code')<small class="field-error">{{ $message }}</small>@enderror</div>
                <button type="submit" class="btn btn--google btn--block"><span aria-hidden="true">G</span>Lanjutkan ke Google</button>
                <p class="login__divider"><span>Sudah aktif?</span></p>
                <a class="btn btn--secondary btn--block" href="{{ route('login') }}">Kembali ke halaman masuk</a>
            </form>
        </section>
    </main>
</body>
</html>
