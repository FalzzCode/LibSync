@extends('layouts.app')
@section('title', 'Detail Transaksi · Ruang Baca')
@section('eyebrow', 'Sirkulasi')
@section('content')
<section class="page form-page">
    <div class="page-header"><div><a class="back-link" href="{{ route('borrowings.index') }}">← Kembali ke transaksi</a><p class="eyebrow">Transaksi #{{ str_pad($borrowing->id,4,'0',STR_PAD_LEFT) }}</p><h1>Detail peminjaman</h1><p>Tinjau dan proses permintaan siswa dengan aman.</p></div></div>
    <article class="detail-card borrowing-detail">
        <x-book-cover :book="$borrowing->book" size="detail" class="detail-card__cover borrowing-detail__cover" loading="eager">
            @if($borrowing->book->cover_image)
                <span class="borrowing-detail__cover-sheen" aria-hidden="true"></span>
                <div class="borrowing-detail__cover-info">
                    <span>LibSync · Sirkulasi</span>
                    <strong>{{ $borrowing->book->title }}</strong>
                    <small>{{ $borrowing->book->category?->name ?? 'Koleksi perpustakaan' }}</small>
                </div>
            @endif
        </x-book-cover>
        <div class="detail-card__content"><div class="detail-card__status">@if($borrowing->status === 'requested')<span class="badge badge--neutral">Menunggu persetujuan</span>@elseif($borrowing->status === 'return_requested')<span class="badge badge--neutral">Permintaan kembali</span>@elseif($borrowing->status === 'returned')<span class="badge badge--success">Dikembalikan</span>@elseif($borrowing->is_overdue)<span class="badge badge--danger">Terlambat</span>@else<span class="badge badge--neutral">Sedang dipinjam</span>@endif</div>
        <dl class="detail-list"><div><dt>Anggota</dt><dd>{{ $borrowing->member->name }}</dd></div><div><dt>Buku</dt><dd>{{ $borrowing->book->title }}</dd></div><div><dt>Tanggal pengajuan</dt><dd>{{ $borrowing->requested_at?->translatedFormat('d F Y') ?? $borrowing->created_at->translatedFormat('d F Y') }}</dd></div><div><dt>Tanggal pinjam</dt><dd>{{ $borrowing->borrowed_at->translatedFormat('d F Y') }}</dd></div><div><dt>Jatuh tempo</dt><dd>{{ $borrowing->due_date->translatedFormat('d F Y') }}</dd></div><div><dt>Denda</dt><dd>Rp{{ number_format($borrowing->fine,0,',','.') }}</dd></div><div><dt>Perpanjangan</dt><dd>{{ $borrowing->extension_count }} kali {{ $borrowing->extension_requested_at ? '· menunggu persetujuan' : '' }}</dd></div></dl>
        @if($borrowing->extension_requested_at)<form class="return-form" method="POST" action="{{ route('borrowings.approve-extension',$borrowing) }}">@csrf<button class="btn btn--secondary" type="submit">Setujui perpanjangan 1 periode</button></form>@endif
        @if($borrowing->status === 'requested')<form class="return-form" method="POST" action="{{ route('borrowings.approve',$borrowing) }}">@csrf<button class="btn btn--primary" type="submit">Setujui peminjaman</button></form>
        @elseif(in_array($borrowing->status,['borrowed','return_requested']))<form class="return-form" method="POST" action="{{ route('borrowings.return',$borrowing) }}">@csrf<label for="returned_at">Tanggal pengembalian<input id="returned_at" type="date" name="returned_at" value="{{ today()->toDateString() }}" min="{{ $borrowing->borrowed_at->toDateString() }}" required></label><button class="btn btn--primary" type="submit">Konfirmasi pengembalian</button></form>@endif
        </div>
    </article>
</section>
@endsection
