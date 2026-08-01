@extends('layouts.app')

@section('title', 'Portal Siswa · LibSync')
@section('eyebrow', 'Portal siswa')

@section('content')
@php
    $overdueCount = $openBorrowings->filter(fn ($borrowing) => $borrowing->is_overdue)->count();
    $nextBorrowing = $openBorrowings->sortBy('due_date')->first();
@endphp
<section class="page student-page">
    <header class="student-hero">
        <div class="student-hero__content">
            <p class="eyebrow">Halo, {{ $member->name }}</p>
            <h1>Semua bacaanmu,<br>lebih teratur.</h1>
            <p>Pantau koleksi, tenggat pengembalian, dan setiap pembaruan perpustakaan dari satu ruang pribadi.</p>
            <div class="student-hero__actions">
                <a class="btn btn--light" href="{{ route('student.catalog') }}"><span aria-hidden="true">▤</span> Jelajahi katalog</a>
                @if($openBorrowings->isNotEmpty())<a class="student-hero__link" href="#pinjaman">Lihat pinjaman <span aria-hidden="true">→</span></a>@endif
            </div>
        </div>
        <div class="student-hero__insight" aria-label="Status akun">
            <span class="student-hero__signal {{ $member->account_status === 'blocked' ? 'student-hero__signal--warning' : '' }}"></span>
            <small>{{ $member->account_status === 'blocked' ? 'Akses peminjaman dibatasi' : 'Akun siap digunakan' }}</small>
            <strong>{{ $member->account_status === 'blocked' ? 'Perlu perhatian' : 'Status baik' }}</strong>
        </div>
        <span class="student-hero__bookmark" aria-hidden="true"><i></i><b></b><em></em></span>
    </header>

    <section class="student-summary" aria-label="Ringkasan aktivitas">
        <a class="student-summary__card" href="#pinjaman"><span class="student-summary__icon">↺</span><span><small>Pinjaman aktif</small><strong>{{ $openBorrowings->count() }}</strong><em>{{ $openBorrowings->isEmpty() ? 'Belum ada buku dipinjam' : 'Lihat detail pinjaman' }}</em></span><b aria-hidden="true">→</b></a>
        <a class="student-summary__card {{ $overdueCount ? 'student-summary__card--alert' : '' }}" href="#pinjaman"><span class="student-summary__icon">!</span><span><small>Perlu dikembalikan</small><strong>{{ $overdueCount }}</strong><em>{{ $overdueCount ? 'Lewat dari jatuh tempo' : 'Tidak ada keterlambatan' }}</em></span><b aria-hidden="true">→</b></a>
        <a class="student-summary__card {{ $unpaidFines->isNotEmpty() ? 'student-summary__card--alert' : '' }}" href="#denda"><span class="student-summary__icon">Rp</span><span><small>Denda terbuka</small><strong>Rp{{ number_format($unpaidFines->sum(fn($fine) => $fine->balance), 0, ',', '.') }}</strong><em>{{ $unpaidFines->count() ? $unpaidFines->count().' tagihan menunggu' : 'Tidak ada tagihan' }}</em></span><b aria-hidden="true">→</b></a>
    </section>

    @if($nextBorrowing)
    <section class="student-next-card" aria-labelledby="nextBorrowingTitle">
        <div><p class="eyebrow">Sedang dibaca</p><h2 id="nextBorrowingTitle">{{ $nextBorrowing->book->title }}</h2><p>Jatuh tempo {{ $nextBorrowing->due_date->translatedFormat('l, d M Y') }}.</p></div>
        <div class="student-next-card__actions">
            <span class="badge {{ $nextBorrowing->is_overdue ? 'badge--danger' : 'badge--success' }}">{{ $nextBorrowing->is_overdue ? 'Terlambat' : 'Masih berjalan' }}</span>
            <a class="btn btn--secondary" href="#pinjaman">Kelola pinjaman</a>
        </div>
    </section>
    @endif

    <div class="student-dashboard-grid">
        <section class="recent-panel student-panel" id="pinjaman">
            <div class="recent-panel__heading student-panel__heading"><div><p class="eyebrow">Sirkulasi</p><h2>Pinjaman saya</h2><p class="student-panel__intro">Ajukan pengembalian atau perpanjangan langsung dari buku yang masih aktif.</p></div><a href="{{ route('student.catalog') }}" class="student-panel__catalog-link"><span aria-hidden="true">⌕</span><span>Ke katalog</span></a></div>
            <div @class(['student-loan-list', 'student-loan-list--empty' => $borrowings->isEmpty()])>
            @forelse($borrowings as $borrowing)
                <article class="student-loan-row">
                    <span class="student-loan-row__icon {{ $borrowing->status === 'returned' ? 'student-loan-row__icon--returned' : ($borrowing->is_overdue ? 'student-loan-row__icon--warning' : '') }}">{{ $borrowing->status === 'returned' ? '✓' : '▤' }}</span>
                    <div class="student-loan-row__detail"><strong>{{ $borrowing->book->title }}</strong><small>{{ $borrowing->status === 'returned' ? 'Dikembalikan '.$borrowing->returned_at?->translatedFormat('d M Y') : ($borrowing->status === 'requested' ? 'Menunggu persetujuan petugas' : 'Jatuh tempo '.$borrowing->due_date->translatedFormat('d M Y')) }}</small></div>
                    <div class="student-loan-row__action">
                    @if($borrowing->status === 'borrowed')
                        <form method="POST" action="{{ route('student.borrowings.return-request',$borrowing) }}">@csrf<button class="btn btn--secondary" type="submit">Ajukan kembali</button></form>
                        @if(!$borrowing->extension_requested_at && $borrowing->extension_count < 1)<form method="POST" action="{{ route('student.borrowings.extension-request',$borrowing) }}">@csrf<button class="text-action" type="submit">Perpanjang</button></form>@elseif($borrowing->extension_requested_at)<small class="cell-muted">Perpanjangan diproses</small>@endif
                    @else
                        <span class="badge {{ $borrowing->status === 'returned' ? 'badge--success' : ($borrowing->is_overdue ? 'badge--danger' : 'badge--neutral') }}">{{ $borrowing->status === 'requested' ? 'Menunggu' : ($borrowing->status === 'return_requested' ? 'Diproses' : ($borrowing->status === 'returned' ? 'Dikembalikan' : 'Terlambat')) }}</span>
                    @endif
                    </div>
                </article>
            @empty
                <div class="empty-state empty-state--student"><span>▤</span><h2>Belum ada pinjaman</h2><p>Mulai dari katalog untuk menemukan buku yang ingin kamu baca.</p><a class="btn btn--primary" href="{{ route('student.catalog') }}">Buka katalog</a></div>
            @endforelse
            </div>
        </section>

        <aside class="student-side-stack">
            <section class="recent-panel student-panel student-panel--compact" id="notifikasi"><div class="recent-panel__heading"><div><p class="eyebrow">Pembaruan</p><h2>Notifikasi</h2></div></div>
            @forelse($notifications as $notification)<a class="student-notification" href="{{ data_get($notification->data,'url',route('student.catalog')) }}"><span>!</span><span><strong>{{ data_get($notification->data,'title') }}</strong><small>{{ data_get($notification->data,'message') }}</small></span></a>@empty<div class="student-empty-note"><span>✓</span><p>Belum ada pembaruan baru.</p></div>@endforelse
            </section>
            <section class="recent-panel student-panel student-panel--compact student-panel--fines" id="denda"><div class="recent-panel__heading"><div><p class="eyebrow">Kewajiban</p><h2>Denda saya</h2></div></div>
            @forelse($unpaidFines as $fine)<div class="student-fine-row"><span><strong>Denda {{ $fine->type === 'late' ? 'keterlambatan' : $fine->type }}</strong><small>{{ $fine->note ?? 'Konfirmasi pembayaran melalui petugas.' }}</small></span><b>Rp{{ number_format($fine->balance,0,',','.') }}</b></div>@empty<div class="student-empty-note"><span>✓</span><p>Tidak ada denda terbuka.</p></div>@endforelse
            </section>
        </aside>
    </div>
</section>
@endsection
