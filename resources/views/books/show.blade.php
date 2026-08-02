@extends('layouts.app')

@section('title', $book->title . ' · LibSync')
@section('eyebrow', 'Koleksi buku')

@section('content')
<section class="page">
    <div class="page-header">
        <div>
            <a class="back-link" href="{{ route('books.index') }}">← Kembali ke koleksi</a>
            <p class="eyebrow">Detail koleksi</p>
            <h1>{{ $book->title }}</h1>
            <p>Informasi bibliografi dan ketersediaan buku.</p>
        </div>
        <div class="page-header__actions"><a href="{{ route('books.edit', $book) }}" class="btn btn--primary">Edit buku</a></div>
    </div>

    <article class="detail-card">
        <x-book-cover :book="$book" size="detail" class="detail-card__cover" loading="eager" />
        <div class="detail-card__content">
            <div class="detail-card__status">
                <span class="badge {{ $book->stock > 0 && ! $book->archived_at ? 'badge--success' : 'badge--danger' }}">
                    {{ $book->archived_at ? 'Diarsipkan' : ($book->stock > 0 ? 'Tersedia' : 'Stok habis') }}
                </span>
                <span>{{ $book->stock }} eksemplar tersedia</span>
            </div>
            <dl class="detail-list">
                <div><dt>Penulis</dt><dd>{{ $book->author }}</dd></div>
                <div><dt>Kategori</dt><dd>{{ $book->category->name }}</dd></div>
                <div><dt>Kode koleksi</dt><dd>{{ $book->book_code ?: 'Belum diisi' }}</dd></div>
                <div><dt>ISBN</dt><dd>{{ $book->isbn ?: 'Belum diisi' }}</dd></div>
                <div><dt>Penerbit</dt><dd>{{ $book->publisher ?: 'Belum diisi' }}</dd></div>
                <div><dt>Tahun terbit</dt><dd>{{ $book->publication_year ?: 'Belum diisi' }}</dd></div>
                <div><dt>Lokasi rak</dt><dd>{{ $book->shelf ?: 'Belum diisi' }}</dd></div>
                <div><dt>Bahasa</dt><dd>{{ $book->language ?: 'Belum diisi' }}</dd></div>
                <div><dt>Jumlah halaman</dt><dd>{{ $book->page_count ? $book->page_count . ' halaman' : 'Belum diisi' }}</dd></div>
            </dl>
            @if ($book->description)
                <div class="detail-card__description"><h2>Deskripsi</h2><p>{{ $book->description }}</p></div>
            @endif
        </div>
    </article>
</section>
@endsection
