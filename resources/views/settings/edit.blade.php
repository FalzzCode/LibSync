@extends('layouts.app')

@section('title', 'Pengaturan · LibSync')
@section('eyebrow', 'Pengaturan')

@section('content')
<section class="page form-page">
    <div class="page-header">
        <div>
            <p class="eyebrow">Administrasi sistem</p>
            <h1>Pengaturan perpustakaan</h1>
            <p>Atur aturan utama sekali saja. Perubahan ini akan dipakai pada transaksi berikutnya.</p>
        </div>
    </div>

    <form action="{{ route('settings.update') }}" method="POST" class="settings-grid">
        @csrf
        @method('PATCH')
        <section class="form-card">
            <div class="form-card__header">
                <p class="eyebrow">Aturan peminjaman</p>
                <h2>Atur sirkulasi buku</h2>
                <p>Gunakan angka sederhana agar petugas dan siswa memahami aturan yang sama.</p>
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label for="max_active_loans">Maksimal buku aktif</label>
                    <input id="max_active_loans" name="max_active_loans" type="number" min="1" max="10" value="{{ old('max_active_loans', $settings['max_active_loans']) }}" required>
                    <small class="field-hint">Jumlah buku yang dapat dipinjam seorang siswa pada waktu bersamaan.</small>
                </div>
                <div class="form-group">
                    <label for="default_loan_days">Lama peminjaman (hari)</label>
                    <input id="default_loan_days" name="default_loan_days" type="number" min="1" max="60" value="{{ old('default_loan_days', $settings['default_loan_days']) }}" required>
                    <small class="field-hint">Tanggal jatuh tempo dihitung otomatis dari hari ini.</small>
                </div>
                <div class="form-group form-group--wide">
                    <label for="fine_per_day">Denda keterlambatan per hari</label>
                    <input id="fine_per_day" name="fine_per_day" type="number" min="0" step="500" value="{{ old('fine_per_day', $settings['fine_per_day']) }}" required>
                    <small class="field-hint">Isi 0 jika sekolah belum menerapkan denda uang.</small>
                </div>
            </div>
        </section>

        <section class="form-card">
            <div class="form-card__header">
                <p class="eyebrow">Akses portal siswa</p>
                <h2>Kode aktivasi</h2>
                <p>Petugas memberikan kode aktivasi saat data anggota sudah dibuat. Kode hanya berlaku sementara.</p>
            </div>
            <div class="form-grid">
                <div class="form-group form-group--wide">
                    <label for="activation_code_days">Masa berlaku kode (hari)</label>
                    <input id="activation_code_days" name="activation_code_days" type="number" min="1" max="30" value="{{ old('activation_code_days', $settings['activation_code_days']) }}" required>
                    <small class="field-hint">Setelah habis masa berlaku, petugas dapat membuat kode baru dari halaman Anggota.</small>
                </div>
            </div>
        </section>

        <div class="form-actions form-actions--sticky">
            <button class="btn btn--primary" type="submit">Simpan pengaturan</button>
        </div>
    </form>
</section>
@endsection
