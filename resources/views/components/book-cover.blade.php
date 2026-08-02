@props([
    'book',
    'size' => 'default',
    'alt' => null,
    'loading' => 'lazy',
])

@php
    $title = $book?->title ?: 'Buku perpustakaan';
    $coverPath = $book?->cover_image;
    $coverUrl = $coverPath ? $book->coverUrl() : null;
    $label = $alt ?: 'Cover '.$title;
@endphp

<div {{ $attributes->merge(['class' => 'book-cover book-cover--'.$size]) }} role="img" aria-label="{{ $label }}">
    @if($coverUrl)
        <img
            src="{{ $coverUrl }}"
            alt=""
            loading="{{ $loading }}"
            decoding="async"
            onerror="this.hidden=true;var cover=this.closest('.book-cover');if(cover){cover.classList.add('book-cover--error');var fallback=cover.querySelector('.book-cover__fallback');if(fallback){fallback.hidden=false;}}"
        >
    @endif
    <span class="book-cover__fallback" @if($coverUrl) hidden @endif aria-hidden="true">
        <strong class="book-cover__fallback-mark">LS</strong>
        <small>Cover belum tersedia</small>
    </span>
    {{ $slot ?? '' }}
</div>
