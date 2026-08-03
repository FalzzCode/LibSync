@extends('layouts.app')

@section('title', 'Panel Pengembang · LibSync')
@section('eyebrow', 'Mode pengembang')

@section('content')
<section class="page">
    <div class="page-header"><div><p class="eyebrow">Hanya lingkungan lokal</p><h1>Panel pengembang</h1><p>Alat observasi dan pengujian lokal. Tidak tersedia pada produksi.</p></div><span class="badge badge--neutral">LOKAL</span></div>

    <section class="recent-panel">
        <div class="recent-panel__heading"><div><p class="eyebrow">Mode pengujian peran</p><h2>Masuk sebagai peran lain</h2></div></div>
        <p class="developer-role-intro">Siapkan akun uji sekali, lalu berpindah antara Admin, Petugas, dan Siswa tanpa logout atau password.</p>
        <div class="developer-actions developer-role-actions">
            <form method="POST" action="{{ route('developer.prepare-role-tests') }}">@csrf<button class="btn btn--primary" type="submit">Siapkan akun peran uji</button><small>Membuat akun lokal yang aman untuk Admin, Petugas, dan Siswa.</small></form>
            @foreach(['admin' => 'Admin', 'staff' => 'Petugas', 'student' => 'Siswa'] as $role => $label)
                <form method="POST" action="{{ route('developer.switch-role') }}">@csrf<input type="hidden" name="role" value="{{ $role }}"><button class="btn btn--secondary" type="submit" @disabled(!isset($testUsers[$role]))>Uji sebagai {{ $label }}</button><small>{{ isset($testUsers[$role]) ? $testUsers[$role]->email : 'Siapkan akun uji terlebih dahulu.' }}</small></form>
            @endforeach
            @if(session()->has('developer_original_user_id'))
                <form method="POST" action="{{ route('developer.restore-user') }}">@csrf<button class="btn btn--secondary" type="submit">Kembali ke akun developer</button><small>Mengakhiri mode pengujian role.</small></form>
            @endif
        </div>
    </section>

    <div class="transaction-stats">@foreach($checks as $label => $value)<article><small>{{ $label }}</small><strong>{{ $value }}</strong><span>Status lingkungan saat ini</span></article>@endforeach</div>
    <div class="developer-grid">
        <section class="recent-panel"><div class="recent-panel__heading"><div><p class="eyebrow">Aksi pengujian</p><h2>Tools aman</h2></div></div><div class="developer-actions"><form method="POST" action="{{ route('developer.seed-demo') }}">@csrf<button type="submit" class="btn btn--primary">Siapkan data uji</button><small>Membuat data contoh hanya bila belum ada.</small></form><form method="POST" action="{{ route('developer.check-overdues') }}">@csrf<button type="submit" class="btn btn--secondary">Jalankan cek terlambat</button><small>Menjalankan scheduler sekarang.</small></form><form method="POST" action="{{ route('developer.clear-cache') }}">@csrf<button type="submit" class="btn btn--secondary">Bersihkan cache</button><small>Refresh config, route, dan view cache.</small></form></div></section>
        <section class="recent-panel"><div class="recent-panel__heading"><div><p class="eyebrow">Database</p><h2>Status migration</h2></div></div><pre class="developer-console">{{ $migrationStatus }}</pre></section>
    </div>
    <section class="recent-panel"><div class="recent-panel__heading"><div><p class="eyebrow">Observasi</p><h2>Log aplikasi terakhir</h2></div></div><pre class="developer-console developer-console--log">{{ $log }}</pre></section>
</section>
@endsection
