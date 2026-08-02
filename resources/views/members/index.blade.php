@extends('layouts.app')

@section('title', 'Anggota · LibSync')
@section('eyebrow', 'Anggota')

@section('content')
<section class="page">
    <div class="page-header">
        <div>
            <p class="eyebrow">Komunitas perpustakaan</p>
            <h1>Daftar anggota</h1>
            <p>Kelola data pembaca dan status akun perpustakaan.</p>
        </div>
        <a class="btn btn--primary" href="{{ route('members.create') }}">+ Tambah anggota</a>
    </div>

    <form class="filter-bar" method="GET" action="{{ route('members.index') }}">
        <div class="search-field">
            <label class="sr-only" for="memberSearch">Cari anggota</label>
            <span aria-hidden="true" data-solar-icon="solar:magnifer-linear">⌕</span>
            <input id="memberSearch" type="search" name="search" value="{{ $search }}" placeholder="Cari nama, email, kelas, atau telepon…" spellcheck="false">
            <button class="search-field__clear" type="button" data-search-clear aria-label="Hapus pencarian" hidden><span data-solar-icon="solar:close-circle-linear" aria-hidden="true">×</span></button>
        </div>
        <div class="filter-bar__controls">
            <button class="btn btn--secondary" type="submit">Cari</button>
            @if($search)<a class="filter-reset" href="{{ route('members.index') }}">Reset</a>@endif
        </div>
    </form>

    <div class="table-card members-table-card">
        <div class="table-card__meta"><span>{{ $members->count() }} anggota {{ $search ? 'ditemukan' : 'terdaftar' }}</span></div>
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr><th>Anggota</th><th>Kelas</th><th>Kontak</th><th>Status</th><th><span class="sr-only">Aksi</span></th></tr>
                </thead>
                <tbody>
                    @forelse($members as $member)
                        @php($memberClass = collect([$member->class, $member->major])->filter()->join(' · '))
                        <tr>
                            <td>
                                <div class="person-cell">
                                    @if(auth()->user()->role === 'admin' && $member->user?->profile_photo_path)
                                        <img class="member-avatar" src="{{ route('profile.photo', $member->user) }}?v={{ $member->user->updated_at?->timestamp }}" alt="Foto profil {{ $member->name }}">
                                    @elseif(auth()->user()->role === 'admin' && $member->user?->avatar_url)
                                        <img class="member-avatar" src="{{ $member->user->avatar_url }}" alt="Foto profil {{ $member->name }}">
                                    @else
                                        <span>{{ strtoupper(substr($member->name, 0, 1)) }}</span>
                                    @endif
                                    <div>
                                        <strong>{{ $member->name }}</strong>
                                        <small class="table-subtitle">{{ $member->email ?? 'Email Google belum diisi' }}</small>
                                        <small class="member-class-mobile">{{ $memberClass ?: 'Kelas belum diisi' }}</small>
                                    </div>
                                </div>
                            </td>
                            <td>{{ $memberClass ?: '—' }}</td>
                            <td>{{ $member->phone }}</td>
                            <td>
                                @if($member->account_status === 'blocked')
                                    <span class="badge badge--danger">Tidak dapat meminjam</span>
                                    <small class="table-subtitle">{{ $member->block_reason ?: 'Perlu diperiksa petugas' }}</small>
                                @elseif(!$member->user_id)
                                    <span class="badge badge--neutral">Belum terhubung</span>
                                    <small class="table-subtitle">{{ $member->email ? 'Siswa dapat masuk Google dengan email ini.' : 'Isi Email Google untuk menghubungkan akun otomatis.' }}</small>
                                @else
                                    <span class="badge badge--success">Aktif</span>
                                    <small class="table-subtitle">Bisa masuk dan meminjam sesuai aturan.</small>
                                @endif
                            </td>
                            <td>
                                <div class="table-actions">
                                    <a class="icon-button" href="{{ route('members.edit', $member) }}" aria-label="Edit {{ $member->name }}">✎</a>
                                    <form class="inline-form js-confirm-delete" data-name="{{ $member->name }}" method="POST" action="{{ route('members.destroy', $member) }}">
                                        @csrf @method('DELETE')
                                        <button class="icon-button icon-button--danger" aria-label="Hapus {{ $member->name }}">×</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><div class="empty-state"><span>♙</span><h2>{{ $search ? 'Anggota tidak ditemukan' : 'Belum ada anggota' }}</h2><p>{{ $search ? 'Coba gunakan kata kunci lain atau reset pencarian.' : 'Daftarkan anggota pertama untuk mulai mengelola komunitas pembaca.' }}</p>@if(!$search)<a class="btn btn--primary" href="{{ route('members.create') }}">+ Tambah anggota</a>@endif</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>
@endsection
