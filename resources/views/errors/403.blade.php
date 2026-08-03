@extends('errors.layout')

@section('title', 'Akses ditolak')

@section('content')
    <p class="error-page__code">403 · Akses ditolak</p>
    <h1 id="errorTitle">Halaman ini bukan bagian dari akses Anda.</h1>
    <p>Akun Anda tetap aman, tetapi peran saat ini tidak memiliki izin untuk membuka halaman tersebut. Kembali ke area kerja untuk melanjutkan.</p>
    <div class="error-page__actions">
        <a class="btn btn--primary" href="{{ auth()->check() ? route('dashboard') : route('login') }}">Kembali ke beranda</a>
        <a class="btn btn--secondary" href="{{ route('login') }}">Ganti akun</a>
    </div>
@endsection
