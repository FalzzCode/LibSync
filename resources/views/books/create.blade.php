@extends('layouts.app')
@section('title', 'Tambah Buku · Ruang Baca')
@section('eyebrow', 'Koleksi buku')
@section('content')
<section class="page form-page"><div class="page-header"><div><a class="back-link" href="{{ route('books.index') }}">← Kembali ke koleksi</a><p class="eyebrow">Koleksi buku</p><h1>Tambah buku baru</h1><p>Lengkapi informasi dasar untuk menambahkan koleksi ke perpustakaan.</p></div></div><form class="form-card" method="POST" action="{{ route('books.store') }}" enctype="multipart/form-data">@csrf @include('books.partials.form', ['book' => null, 'submitLabel' => 'Simpan buku'])</form></section>
@endsection
