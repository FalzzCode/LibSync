@extends('layouts.app')

@section('title', 'Transaksi · LibSync')
@section('eyebrow', 'Sirkulasi')

@section('content')
<section class="page">
    <div class="page-header">
        <div>
            <p class="eyebrow">Sirkulasi perpustakaan</p>
            <h1>Transaksi peminjaman</h1>
            <p>Pantau peminjaman aktif, keterlambatan, dan permintaan siswa dalam satu tempat.</p>
        </div>
        <a href="{{ route('borrowings.create') }}" class="btn btn--primary">+ Catat peminjaman</a>
    </div>

    <form class="filter-bar filter-bar--transaction" method="GET">
        <div class="search-field">
            <span aria-hidden="true" data-solar-icon="solar:magnifer-linear">⌕</span>
            <label class="sr-only" for="transactionSearch">Cari transaksi</label>
            <input id="transactionSearch" type="search" name="search" value="{{ request('search') }}" placeholder="Cari anggota atau judul buku…" maxlength="120" spellcheck="false">
            <button class="search-field__clear" type="button" data-search-clear aria-label="Hapus pencarian" hidden><span data-solar-icon="solar:close-circle-linear" aria-hidden="true">×</span></button>
        </div>
        <div class="filter-bar__controls">
            <select name="status" aria-label="Status transaksi">
                <option value="">Semua status</option>
                <option value="requested" @selected(request('status') === 'requested')>Menunggu persetujuan</option>
                <option value="return_requested" @selected(request('status') === 'return_requested')>Pengembalian diajukan</option>
                <option value="extension_requested" @selected(request('status') === 'extension_requested')>Perpanjangan diajukan</option>
                <option value="borrowed" @selected(request('status') === 'borrowed')>Sedang dipinjam</option>
                <option value="overdue" @selected(request('status') === 'overdue')>Terlambat</option>
                <option value="returned" @selected(request('status') === 'returned')>Dikembalikan</option>
            </select>
            <label class="date-filter">Dari <input type="date" name="from" value="{{ request('from') }}"></label>
            <label class="date-filter">Sampai <input type="date" name="until" value="{{ request('until') }}"></label>
            <button class="btn btn--secondary" type="submit">Terapkan</button>
            @if(request()->hasAny(['search', 'status', 'from', 'until']))
                <a class="filter-reset" href="{{ route('borrowings.index') }}">Reset</a>
            @endif
        </div>
    </form>

    <div class="table-card borrowings-table-card">
        <div class="table-card__meta"><span>{{ $borrowings->count() }} transaksi ditemukan</span><span>Denda: Rp{{ number_format($borrowings->sum('fine'), 0, ',', '.') }}</span></div>
        <div class="table-wrapper">
            <table class="data-table">
                <thead><tr><th>Anggota & buku</th><th>Tanggal pinjam</th><th>Jatuh tempo</th><th>Status</th><th>Denda</th><th><span class="sr-only">Aksi</span></th></tr></thead>
                <tbody>
                    @forelse($borrowings as $borrowing)
                        <tr>
                            <td><div><strong>{{ $borrowing->member->name }}</strong><small class="table-subtitle">{{ $borrowing->book->title }}</small></div></td>
                            <td>{{ $borrowing->borrowed_at?->translatedFormat('d M Y') ?? 'Menunggu' }}</td>
                            <td><span @class(['date-overdue' => $borrowing->is_overdue])>{{ $borrowing->due_date?->translatedFormat('d M Y') ?? 'Menunggu' }}</span></td>
                            <td>
                                @if($borrowing->status === 'requested')<span class="badge badge--neutral">Menunggu persetujuan</span>
                                @elseif($borrowing->status === 'return_requested')<span class="badge badge--neutral">Pengembalian diajukan</span>
                                @elseif($borrowing->extension_requested_at)<span class="badge badge--neutral">Perpanjangan diajukan</span>
                                @elseif($borrowing->status === 'returned')<span class="badge badge--success">Dikembalikan</span>
                                @elseif($borrowing->is_overdue)<span class="badge badge--danger">Terlambat</span>
                                @else<span class="badge badge--neutral">Dipinjam</span>
                                @endif
                            </td>
                            <td>Rp{{ number_format($borrowing->fine, 0, ',', '.') }}</td>
                            <td><a class="icon-button" href="{{ route('borrowings.show', $borrowing) }}" aria-label="Lihat transaksi {{ $borrowing->id }}">→</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><div class="empty-state"><span>↺</span><h2>{{ request()->hasAny(['search', 'status', 'from', 'until']) ? 'Transaksi tidak ditemukan' : 'Belum ada transaksi' }}</h2><p>{{ request()->hasAny(['search', 'status', 'from', 'until']) ? 'Coba ubah kata kunci, status, atau rentang tanggal.' : 'Catat peminjaman pertama untuk mulai melihat riwayat sirkulasi.' }}</p>@if(!request()->hasAny(['search', 'status', 'from', 'until']))<a class="btn btn--primary" href="{{ route('borrowings.create') }}">+ Catat peminjaman</a>@endif</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>
@endsection
