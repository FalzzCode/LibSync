@extends('layouts.app')

@section('title', 'Pengguna · LibSync')
@section('eyebrow', 'Administrasi')

@section('content')
<section class="page">
    <div class="page-header">
        <div>
            <p class="eyebrow">Administrasi sistem</p>
            <h1>Pengguna</h1>
            <p>Atur akun dan peran untuk akses ke sistem perpustakaan.</p>
        </div>
        <a class="btn btn--primary" href="{{ route('users.create') }}">+ Tambah pengguna</a>
    </div>

    <form class="filter-bar" method="GET" action="{{ route('users.index') }}">
        <div class="search-field">
            <label class="sr-only" for="userSearch">Cari pengguna</label>
            <span aria-hidden="true" data-solar-icon="solar:magnifer-linear">⌕</span>
            <input id="userSearch" type="search" name="search" value="{{ $search }}" placeholder="Cari nama, email, atau peran…" spellcheck="false">
            <button class="search-field__clear" type="button" data-search-clear aria-label="Hapus pencarian" hidden><span data-solar-icon="solar:close-circle-linear" aria-hidden="true">×</span></button>
        </div>
        <div class="filter-bar__controls">
            <button class="btn btn--secondary" type="submit">Cari</button>
            @if($search)<a class="filter-reset" href="{{ route('users.index') }}">Reset</a>@endif
        </div>
    </form>

    <div class="table-card">
        <div class="table-card__meta"><span>{{ $users->count() }} pengguna {{ $search ? 'ditemukan' : 'terdaftar' }}</span></div>
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr><th>Pengguna</th><th>Email</th><th>Peran</th><th><span class="sr-only">Aksi</span></th></tr>
                </thead>
                <tbody>
                    @forelse($users as $user)
                        <tr>
                            <td>
                                <div class="person-cell">
                                    @if($user->profile_photo_path)
                                        <img class="user-avatar" src="{{ route('profile.photo', $user) }}?v={{ $user->updated_at?->timestamp }}" alt="Foto profil {{ $user->name }}">
                                    @elseif($user->avatar_url)
                                        <img class="user-avatar" src="{{ $user->avatar_url }}" alt="Foto profil {{ $user->name }}">
                                    @else
                                        <span>{{ strtoupper(substr($user->name, 0, 1)) }}</span>
                                    @endif
                                    <strong>{{ $user->name }}</strong>
                                </div>
                            </td>
                            <td>{{ $user->email }}</td>
                            <td><span class="role-badge">{{ ucfirst($user->role) }}</span></td>
                            <td>
                                <div class="table-actions">
                                    <a class="icon-button" href="{{ route('users.edit', $user) }}" aria-label="Edit {{ $user->name }}">✎</a>
                                    @if($user->id !== auth()->id())
                                        <form class="inline-form js-confirm-delete" data-name="{{ $user->name }}" method="POST" action="{{ route('users.destroy', $user) }}">
                                            @csrf @method('DELETE')
                                            <button class="icon-button icon-button--danger" aria-label="Hapus {{ $user->name }}">×</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4"><div class="empty-state"><span>◉</span><h2>{{ $search ? 'Pengguna tidak ditemukan' : 'Belum ada pengguna' }}</h2><p>{{ $search ? 'Coba gunakan kata kunci lain atau reset pencarian.' : 'Tambahkan pengguna untuk memberi akses ke sistem.' }}</p>@if(!$search)<a class="btn btn--primary" href="{{ route('users.create') }}">+ Tambah pengguna</a>@endif</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>
@endsection
