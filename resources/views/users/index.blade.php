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

    <div class="table-card">
        <div class="table-card__meta"><span>{{ $users->count() }} pengguna terdaftar</span></div>
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
                        <tr><td colspan="4"><div class="empty-state"><span>◉</span><h2>Belum ada pengguna</h2><p>Tambahkan pengguna untuk memberi akses ke sistem.</p><a class="btn btn--primary" href="{{ route('users.create') }}">+ Tambah pengguna</a></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>
@endsection
