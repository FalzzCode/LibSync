@extends('errors.layout')

@section('title', 'Halaman tidak ditemukan')

@section('content')
    <p class="error-page__code">404 · Tidak ditemukan</p>
    <h1 id="errorTitle">Halaman yang Anda cari tidak ada.</h1>
    <p>Tautan mungkin sudah berubah atau data tersebut telah dipindahkan. Kembali ke area kerja dan pilih menu yang tersedia.</p>
    <div class="error-page__actions">
        <a class="btn btn--primary" href="{{ auth()->check() ? route('dashboard') : route('login') }}">Kembali ke beranda</a>
        <a class="btn btn--secondary" href="{{ route('login') }}">Halaman masuk</a>
    </div>
@endsection
