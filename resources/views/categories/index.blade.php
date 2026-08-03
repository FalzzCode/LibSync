@extends('layouts.app')

@section('title', 'Kategori · Ruang Baca')
@section('eyebrow', 'Kategori')

@section('content')
<section class="page">
    <div class="page-header">
        <div>
            <p class="eyebrow">Pengelompokan koleksi</p>
            <h1>Kategori buku</h1>
            <p>Susun koleksi agar setiap buku lebih mudah ditemukan.</p>
        </div>
        <a href="{{ route('categories.create') }}" class="btn btn--primary">+ Tambah kategori</a>
    </div>

    <form class="filter-bar" method="GET" action="{{ route('categories.index') }}">
        <div class="search-field">
            <label class="sr-only" for="categorySearch">Cari kategori</label>
            <span aria-hidden="true" data-solar-icon="solar:magnifer-linear">⌕</span>
            <input id="categorySearch" type="search" name="search" value="{{ $search }}" maxlength="120" placeholder="Cari nama kategori…" spellcheck="false">
            <button class="search-field__clear" type="button" data-search-clear aria-label="Hapus pencarian" hidden><span data-solar-icon="solar:close-circle-linear" aria-hidden="true">×</span></button>
        </div>
        <div class="filter-bar__controls">
            <button class="btn btn--secondary" type="submit">Cari</button>
            @if($search)<a class="filter-reset" href="{{ route('categories.index') }}">Reset</a>@endif
        </div>
    </form>

    <div class="table-card">
        <div class="table-card__meta"><span>{{ $categories->count() }} kategori {{ $search ? 'ditemukan' : 'terdaftar' }}</span></div>
        <div class="table-wrapper">
            <table class="data-table">
                <thead><tr><th>Nama kategori</th><th>Dibuat</th><th><span class="sr-only">Aksi</span></th></tr></thead>
                <tbody>
                    @forelse($categories as $category)
                        <tr>
                            <td><strong>{{ $category->name }}</strong></td>
                            <td>{{ $category->created_at?->translatedFormat('d M Y') }}</td>
                            <td>
                                <div class="table-actions">
                                    <a class="icon-button" href="{{ route('categories.edit', $category) }}" aria-label="Edit {{ $category->name }}"><span data-solar-icon="solar:pen-new-square-linear">✎</span></a>
                                    <form class="inline-form js-confirm-delete" data-name="{{ $category->name }}" method="POST" action="{{ route('categories.destroy', $category) }}">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="icon-button icon-button--danger" aria-label="Hapus {{ $category->name }}"><span data-solar-icon="solar:trash-bin-trash-linear">×</span></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3"><div class="empty-state"><span data-solar-icon="solar:widget-5-linear">◇</span><h2>Belum ada kategori</h2><p>{{ $search ? 'Tidak ada kategori yang cocok dengan pencarian.' : 'Buat kategori pertama untuk mulai merapikan koleksi.' }}</p><a class="btn btn--primary" href="{{ route('categories.create') }}">+ Tambah kategori</a></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>
@endsection
