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

    @if(session('activation_code'))
        <section class="activation-code-notice" role="status">
            <div><p class="eyebrow">Kode aktivasi siswa</p><strong>{{ session('activation_member_name') }}</strong><p>Berikan kode ini secara pribadi. Siswa memasukkan NIS dan kode ini, lalu melanjutkan dengan Google. Berlaku sampai {{ session('activation_expires_at')?->translatedFormat('d M Y, H:i') }}.</p></div>
            <code>{{ session('activation_code') }}</code>
        </section>
    @endif

    <div class="table-card members-table-card">
        <div class="table-card__meta"><span>{{ $members->count() }} anggota terdaftar</span></div>
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
                                        <small class="table-subtitle">{{ $member->nis ?? $member->email ?? 'Identitas belum diisi' }}</small>
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
                                    <span class="badge badge--neutral">Belum aktivasi portal</span>
                                    <small class="table-subtitle">Berikan kode aktivasi saat siswa siap masuk.</small>
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
                        <tr><td colspan="5"><div class="empty-state"><span>♙</span><h2>Belum ada anggota</h2><p>Daftarkan anggota pertama untuk mulai mengelola komunitas pembaca.</p><a class="btn btn--primary" href="{{ route('members.create') }}">+ Tambah anggota</a></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>
@endsection
