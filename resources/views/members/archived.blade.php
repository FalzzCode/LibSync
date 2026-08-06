@extends('layouts.app')

@section('title', 'Arsip Anggota · LibSync')
@section('eyebrow', 'Anggota')

@section('content')
<section class="page">
    <div class="page-header">
        <div>
            <a class="back-link" href="{{ route('members.index') }}">← Kembali ke anggota aktif</a>
            <p class="eyebrow">Arsip anggota</p>
            <h1>Anggota yang diarsipkan</h1>
            <p>Data lama tetap disimpan. Admin atau petugas dapat mengaktifkannya kembali.</p>
        </div>
        <a class="btn btn--primary" href="{{ route('members.create') }}">+ Tambah anggota</a>
    </div>

    <div class="table-card">
        <div class="table-card__meta"><span>{{ $members->count() }} anggota diarsipkan</span></div>
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr><th scope="col">Anggota</th><th scope="col">Email Google</th><th scope="col">Diarsipkan</th><th scope="col"><span class="sr-only">Aksi</span></th></tr>
                </thead>
                <tbody>
                    @forelse($members as $member)
                        <tr>
                            <td><strong>{{ $member->name }}</strong><small class="table-subtitle">{{ $member->class ?: 'Kelas belum diisi' }}{{ $member->major ? ' · '.$member->major : '' }}</small></td>
                            <td>{{ $member->email ?: 'Email Google belum diisi' }}</td>
                            <td>{{ $member->deleted_at?->translatedFormat('d M Y, H:i') }}</td>
                            <td>
                                <form method="POST" action="{{ route('members.restore', $member) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button class="btn btn--secondary" type="submit">Pulihkan</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4"><div class="empty-state"><span>♙</span><h2>Arsip masih kosong</h2><p>Anggota yang dihapus akan muncul di sini dan bisa dipulihkan kembali.</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>
@endsection
