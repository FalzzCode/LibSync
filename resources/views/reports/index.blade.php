@extends('layouts.app')

@section('title', 'Laporan · LibSync')
@section('eyebrow', 'Laporan')

@section('content')
<section class="page">
    <div class="page-header">
        <div><p class="eyebrow">Ringkasan sekolah</p><h1>Laporan perpustakaan</h1><p>Lihat ringkasan operasional, lalu unduh data yang diperlukan tanpa membuka spreadsheet terlebih dahulu.</p></div>
    </div>
    <form class="filter-bar" method="GET">
        <div class="filter-bar__controls">
            <label class="date-filter">Dari <input type="date" name="from" value="{{ $from->toDateString() }}"></label>
            <label class="date-filter">Sampai <input type="date" name="until" value="{{ $until->toDateString() }}"></label>
            <button class="btn btn--secondary" type="submit">Tampilkan</button>
        </div>
    </form>
    <div class="report-period">Periode laporan: <strong>{{ $from->translatedFormat('d M Y') }} – {{ $until->translatedFormat('d M Y') }}</strong></div>
    <div class="stats-grid report-stats">
        <article class="stat-card"><span class="stat-card__icon icon--teal" data-solar-icon="solar:book-2-linear" aria-hidden="true">↗</span><span><small>Peminjaman</small><strong>{{ number_format($summary['borrowed'], 0, ',', '.') }}</strong><em>Dicatat pada periode ini</em></span></article>
        <article class="stat-card"><span class="stat-card__icon icon--blue" data-solar-icon="solar:check-circle-linear" aria-hidden="true">✓</span><span><small>Pengembalian</small><strong>{{ number_format($summary['returned'], 0, ',', '.') }}</strong><em>Transaksi selesai</em></span></article>
        <article class="stat-card"><span class="stat-card__icon icon--rose" data-solar-icon="solar:danger-triangle-linear" aria-hidden="true">!</span><span><small>Masih terlambat</small><strong>{{ number_format($summary['overdue'], 0, ',', '.') }}</strong><em>Perlu ditindaklanjuti</em></span></article>
        <article class="stat-card"><span class="stat-card__icon icon--gold" data-solar-icon="solar:wallet-money-linear" aria-hidden="true">Rp</span><span><small>Denda dibayar</small><strong>Rp{{ number_format($summary['fines_paid'], 0, ',', '.') }}</strong><em>Pembayaran pada periode ini</em></span></article>
    </div>
    <section class="action-panel report-downloads">
        <p class="eyebrow">Unduh data</p><h2>Siap dipakai untuk administrasi</h2>
        <div class="quick-actions">
            <a href="{{ route('reports.borrowings.csv', ['from' => $from->toDateString(), 'until' => $until->toDateString()]) }}"><span data-solar-icon="solar:download-linear" aria-hidden="true">↓</span><strong>Data peminjaman</strong><small>CSV sesuai periode di atas</small></a>
            <a href="{{ route('reports.fine-payments.csv', ['from' => $from->toDateString(), 'until' => $until->toDateString()]) }}"><span data-solar-icon="solar:download-linear" aria-hidden="true">↓</span><strong>Pembayaran denda</strong><small>CSV sesuai periode di atas</small></a>
            <a href="{{ route('imports.create') }}"><span data-solar-icon="solar:upload-linear" aria-hidden="true">↑</span><strong>Impor data</strong><small>Tambahkan buku atau anggota</small></a>
        </div>
    </section>
</section>
@endsection
