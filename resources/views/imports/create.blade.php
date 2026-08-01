@extends('layouts.app')
@section('title', 'Impor Data · Ruang Baca')
@section('eyebrow', 'Administrasi data')
@section('content')
<section class="page form-page"><div class="page-header"><div><p class="eyebrow">Migrasi data</p><h1>Impor CSV</h1><p>Masukkan data awal buku atau anggota dalam jumlah banyak dengan format yang konsisten.</p></div></div>
<div class="form-card form-card--compact"><div class="form-card__header"><h2>Unggah berkas</h2><p>Baris pertama wajib berisi nama kolom. Maksimal 2 MB.</p></div><form action="{{ route('imports.store') }}" method="POST" enctype="multipart/form-data">@csrf<div class="form-grid"><div class="form-group form-group--wide"><label for="type">Jenis data</label><select id="type" name="type"><option value="books">Buku</option><option value="members">Anggota</option></select></div><div class="form-group form-group--wide"><label for="file">Berkas CSV</label><input id="file" name="file" type="file" accept=".csv,text/csv" required><small class="field-hint">Buku: title, author, category, stock (opsional: book_code, publisher, publication_year). Anggota: name, phone (opsional: nis, email, class, address, major, entry_year).</small></div></div><div class="form-actions"><button class="btn btn--primary" type="submit">Impor data</button></div></form></div></section>
@endsection
