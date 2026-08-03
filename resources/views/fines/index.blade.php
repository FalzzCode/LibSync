@extends('layouts.app')

@section('title', 'Denda · Ruang Baca')
@section('eyebrow', 'Keuangan')

@section('content')
<section class="page">
    <div class="page-header">
        <div><p class="eyebrow">Keuangan anggota</p><h1>Denda & pembayaran</h1><p>Catat pelunasan secara transparan dan pantau saldo denda anggota.</p></div>
        <div class="page-header__actions"><a class="btn btn--secondary" href="{{ route('reports.fine-payments.csv') }}">Ekspor pembayaran</a></div>
    </div>
    <form class="filter-bar" method="GET" action="{{ route('fines.index') }}">
        <div class="search-field"><label class="sr-only" for="fineSearch">Cari denda</label><span aria-hidden="true" data-solar-icon="solar:magnifer-linear">⌕</span><input id="fineSearch" type="search" name="search" value="{{ request('search') }}" maxlength="120" placeholder="Cari nama anggota atau judul buku…" spellcheck="false"><button class="search-field__clear" type="button" data-search-clear aria-label="Hapus pencarian" hidden><span data-solar-icon="solar:close-circle-linear" aria-hidden="true">×</span></button></div>
        <div class="filter-bar__controls"><select name="status" aria-label="Status denda" onchange="this.form.submit()"><option value="">Semua status</option>@foreach(['unpaid'=>'Belum lunas','partial'=>'Sebagian','paid'=>'Lunas'] as $value=>$label)<option value="{{ $value }}" @selected(request('status')===$value)>{{ $label }}</option>@endforeach</select><button class="btn btn--secondary" type="submit">Cari</button>@if(request()->hasAny(['search','status']))<a class="filter-reset" href="{{ route('fines.index') }}">Reset</a>@endif</div>
    </form>
    <div class="table-card"><div class="table-wrapper"><table class="data-table"><thead><tr><th>Anggota / transaksi</th><th>Total denda</th><th>Terbayar</th><th>Sisa</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
    @forelse($fines as $fine)<tr><td><strong>{{ $fine->member->name }}</strong><span class="table-subtitle">{{ $fine->borrowing?->book?->title ?? 'Penyesuaian denda' }}</span></td><td>Rp{{ number_format($fine->amount,0,',','.') }}</td><td>Rp{{ number_format($fine->paid_amount,0,',','.') }}</td><td><strong>Rp{{ number_format($fine->balance,0,',','.') }}</strong></td><td><span @class(['badge','badge--success'=>$fine->status==='paid','badge--danger'=>$fine->status!=='paid'])>{{ $fine->status==='paid' ? 'Lunas' : ($fine->status==='partial' ? 'Sebagian' : 'Belum lunas') }}</span></td><td>@if($fine->balance)<details><summary class="text-action">Catat bayar</summary><form method="POST" action="{{ route('fines.pay',$fine) }}" class="payment-form">@csrf<input name="amount" type="number" min="1" max="{{ $fine->balance }}" value="{{ $fine->balance }}" aria-label="Nominal pembayaran"><select name="method" aria-label="Metode pembayaran"><option value="cash">Tunai</option><option value="transfer">Transfer</option><option value="qris">QRIS</option><option value="waived">Dibebaskan</option></select><button class="btn btn--primary" type="submit">Simpan</button></form></details>@else<small class="cell-muted">Selesai</small>@endif</td></tr>@empty<tr><td colspan="6"><div class="empty-state empty-state--small"><h2>{{ request()->hasAny(['search', 'status']) ? 'Denda tidak ditemukan' : 'Belum ada denda' }}</h2><p>{{ request()->hasAny(['search', 'status']) ? 'Coba gunakan kata kunci lain atau pilih status yang berbeda.' : 'Denda keterlambatan akan muncul otomatis setelah pengembalian.' }}</p></div></td></tr>@endforelse
    </tbody></table></div></div>
</section>
@endsection
