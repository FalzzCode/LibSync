@extends('layouts.app')

@section('title', 'Katalog Buku · LibSync')
@section('eyebrow', 'Portal siswa')

@section('content')
<section class="page student-catalog-page">
    <header class="student-catalog-head">
        <div><p class="eyebrow">Koleksi perpustakaan</p><h1>Temukan bacaan berikutnya.</h1><p>Cari koleksi yang tersedia, lalu ajukan peminjaman atau masuk ke daftar tunggu dengan satu langkah.</p></div>
        <a class="student-catalog-head__back" href="{{ route('student.dashboard') }}"><span aria-hidden="true">←</span> Kembali ke beranda</a>
    </header>
    <form class="student-catalog-search" method="GET" role="search">
        <label class="sr-only" for="studentBookSearch">Cari buku</label>
        <span aria-hidden="true">⌕</span><input id="studentBookSearch" name="search" value="{{ request('search') }}" placeholder="Cari judul atau penulis…" autocomplete="off">
        @if(request()->filled('search'))<a href="{{ route('student.catalog') }}" class="student-catalog-search__clear">Hapus</a>@endif
        <button class="btn btn--primary" type="submit">Cari</button>
    </form>
    <div class="student-catalog-meta"><span>{{ $books->count() }} buku ditemukan</span><span>Stok diperbarui dari koleksi perpustakaan</span></div>
    <div class="book-catalog student-book-catalog">
        @forelse($books as $book)
        <article class="catalog-card student-book-card">
            <div class="catalog-card__cover">@if($book->cover_image)<img src="{{ asset('storage/'.$book->cover_image) }}" alt="Cover {{ $book->title }}">@else<span>Lib<br>Sync</span>@endif</div>
            <div class="student-book-card__content"><p>{{ $book->category->name }}</p><h2>{{ $book->title }}</h2><small>{{ $book->author }}</small>
                <div class="student-book-card__footer"><span class="badge {{ $book->stock > 0 ? 'badge--success' : 'badge--danger' }}">{{ $book->stock > 0 ? $book->stock.' tersedia' : 'Stok habis' }}</span>
                @if($book->stock > 0)<form method="POST" action="{{ route('student.borrowings.store',$book) }}">@csrf<button class="btn btn--primary" type="submit">Ajukan pinjam <span aria-hidden="true">→</span></button></form>
                @else<form method="POST" action="{{ route('student.reservations.store',$book) }}">@csrf<button class="btn btn--secondary" type="submit">Daftar tunggu</button></form>@endif</div>
            </div>
        </article>
        @empty
        <div class="empty-state student-catalog-empty"><span>⌕</span><h2>Buku tidak ditemukan</h2><p>Coba gunakan judul, nama penulis, atau kata kunci yang lebih singkat.</p><a class="btn btn--secondary" href="{{ route('student.catalog') }}">Lihat semua buku</a></div>
        @endforelse
    </div>
</section>
@endsection
