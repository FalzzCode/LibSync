@extends('layouts.app')

@section('title', 'Edit Anggota · LibSync')
@section('eyebrow', 'Anggota')

@section('content')
<section class="page form-page">
    <div class="page-header"><div><a class="back-link" href="{{ route('members.index') }}">← Kembali ke anggota</a><p class="eyebrow">Anggota</p><h1>Edit anggota</h1><p>Perbarui profil {{ $member->name }}.</p></div></div>
    <form class="form-card" method="POST" action="{{ route('members.update', $member) }}">@csrf @method('PUT') @include('members.partials.form', ['member' => $member, 'submitLabel' => 'Simpan perubahan'])</form>

    <section class="form-card activation-admin-card">
        <div class="form-card__header">
            <h2>Status portal siswa</h2>
            @if($member->user?->google_id)
                <p>Aktif sebagai <strong>{{ $member->user->email }}</strong>. Google sudah terhubung pada {{ optional($member->activated_at)->translatedFormat('d M Y') ?? 'akun lama' }}.</p>
            @elseif($member->user)
                <p>Akun portal lama aktif sebagai <strong>{{ $member->user->email }}</strong>, tetapi belum terhubung ke Google. Gunakan akun ini untuk pengujian lokal atau hubungi admin untuk migrasi akun.</p>
            @else
                <p>{{ $member->email ? 'Email Google sudah tersimpan. Siswa dapat masuk memakai akun tersebut.' : 'Portal belum terhubung. Tambahkan Email Google di profil anggota agar siswa dapat masuk.' }}</p>
            @endif
        </div>
    </section>
</section>
@endsection
