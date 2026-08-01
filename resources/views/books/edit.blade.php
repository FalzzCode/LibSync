@extends('layouts.app')
@section('title', 'Edit Buku · Ruang Baca')
@section('eyebrow', 'Koleksi buku')
@section('content')
<section class="page form-page"><div class="page-header"><div><a class="back-link" href="{{ route('books.show', $book) }}">← Kembali ke detail buku</a><p class="eyebrow">Koleksi buku</p><h1>Edit informasi buku</h1><p>Perbarui data <strong>{{ $book->title }}</strong> dan simpan perubahan saat selesai.</p></div></div><form class="form-card" method="POST" action="{{ route('books.update', $book) }}" enctype="multipart/form-data">@csrf @method('PUT') @include('books.partials.form', ['book' => $book, 'submitLabel' => 'Simpan perubahan'])</form></section>
@endsection

