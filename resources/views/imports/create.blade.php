@extends('layouts.app')
@section('title', 'Impor Data · Ruang Baca')
@section('eyebrow', 'Administrasi data')
@section('content')
<section class="page form-page import-page">
    <div class="page-header">
        <div>
            <p class="eyebrow">Migrasi data</p>
            <h1>Impor CSV</h1>
            <p>Tambahkan banyak buku atau anggota sekaligus dengan file yang sudah disiapkan.</p>
        </div>
    </div>

    <div class="import-workspace">
        <form class="form-card import-upload-card" action="{{ route('imports.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="import-card__header">
                <span class="import-card__icon" data-solar-icon="solar:upload-minimalistic-linear" aria-hidden="true">↑</span>
                <div>
                    <p class="eyebrow">Mulai dari sini</p>
                    <h2>Unggah file CSV</h2>
                    <p>Pilih jenis data, lalu kirim file dengan nama kolom yang sesuai.</p>
                </div>
            </div>

            <div class="import-form__body">
                <div class="import-step">
                    <span class="import-step__number" aria-hidden="true">1</span>
                    <div class="form-group">
                        <label for="type">Jenis data</label>
                        <select id="type" name="type" required>
                            <option value="books" @selected(old('type', 'books') === 'books')>Buku</option>
                            <option value="members" @selected(old('type') === 'members')>Anggota</option>
                        </select>
                        <small class="field-hint">Pilih sesuai isi file yang akan diunggah.</small>
                    </div>
                </div>

                <div class="import-step">
                    <span class="import-step__number" aria-hidden="true">2</span>
                    <div class="form-group">
                        <label for="file">File CSV</label>
                        <div class="import-file-picker" data-import-file-picker>
                            <input class="import-file-input" id="file" name="file" type="file" accept=".csv,text/csv" aria-describedby="fileHint" required>
                            <label class="import-file-picker__label" for="file">
                                <span class="import-file-picker__icon" data-solar-icon="solar:upload-minimalistic-linear" aria-hidden="true">↑</span>
                                <span><strong data-import-file-name>Pilih file CSV</strong><small data-import-file-status>Belum ada file dipilih</small></span>
                            </label>
                        </div>
                        <small class="field-hint" id="fileHint">Gunakan CSV UTF-8 dengan pemisah koma (,). Ukuran maksimal 2 MB.</small>
                    </div>
                </div>
            </div>

            <div class="form-actions import-card__actions">
                <button class="btn btn--primary" type="submit"><span data-solar-icon="solar:upload-minimalistic-linear" aria-hidden="true">↑</span>Impor data</button>
            </div>
        </form>

        <aside class="form-card import-guide-card" aria-labelledby="importGuideTitle">
            <div class="import-guide__header">
                <span data-solar-icon="solar:clipboard-list-linear" aria-hidden="true">☷</span>
                <div>
                    <p class="eyebrow">Panduan singkat</p>
                    <h2 id="importGuideTitle">Cara impor data</h2>
                    <p>Ikuti empat langkah ini agar data masuk dengan rapi. File lama dengan nama kolom Inggris tetap didukung.</p>
                </div>
            </div>

            <div class="import-guide__body">
                <ol class="import-steps">
                    <li><span class="import-steps__number" aria-hidden="true">1</span><div><strong>Siapkan file</strong><p>Buat file di Excel atau Google Sheets, lalu simpan sebagai CSV UTF-8.</p></div></li>
                    <li><span class="import-steps__number" aria-hidden="true">2</span><div><strong>Gunakan nama kolom yang benar</strong><p>Baris pertama harus berisi nama kolom sesuai format di bawah.</p></div></li>
                    <li><span class="import-steps__number" aria-hidden="true">3</span><div><strong>Pilih jenis data</strong><p>Pastikan pilihan Buku atau Anggota sesuai isi file.</p></div></li>
                    <li><span class="import-steps__number" aria-hidden="true">4</span><div><strong>Periksa hasilnya</strong><p>Baris yang valid akan disimpan; baris dengan format salah akan dilewati.</p></div></li>
                </ol>

                <div class="import-format">
                    <h3>Kolom yang tersedia</h3>
                    <div class="import-format__item">
                        <strong>Buku</strong>
                        <small>Hijau = wajib · abu-abu = opsional</small>
                        <div class="import-format__fields"><code class="is-required">judul</code><code class="is-required">penulis</code><code class="is-required">kategori</code><code class="is-required">stok</code><code>kode_buku</code><code>penerbit</code><code>tahun_terbit</code></div>
                    </div>
                    <div class="import-format__item">
                        <strong>Anggota</strong>
                        <small>Email Google disarankan untuk menghubungkan akun siswa. Nomor telepon bersifat opsional.</small>
                        <div class="import-format__fields"><code class="is-required">nama</code><code>nomor_telepon</code><code>email</code><code>kelas</code><code>jurusan</code><code>alamat</code><code>tahun_masuk</code></div>
                    </div>
                </div>

                <div class="import-template">
                    <div>
                        <strong>Mulai dari file siap pakai</strong>
                        <p>Contoh buku Indonesia dengan kolom yang sudah cocok untuk LibSync.</p>
                    </div>
                    <a class="btn btn--secondary" href="{{ asset('templates/contoh-buku.csv') }}" download>
                        <span data-solar-icon="solar:download-linear" aria-hidden="true">↓</span>
                        Unduh contoh buku
                    </a>
                </div>

                <div class="import-template">
                    <div>
                        <strong>Contoh data anggota</strong>
                        <p>Contoh anggota berbahasa Indonesia, tanpa kolom NIS.</p>
                    </div>
                    <a class="btn btn--secondary" href="{{ asset('templates/contoh-anggota.csv') }}" download>
                        <span data-solar-icon="solar:download-linear" aria-hidden="true">↓</span>
                        Unduh contoh anggota
                    </a>
                </div>

                <details class="import-example">
                    <summary><span data-solar-icon="solar:code-square-linear" aria-hidden="true">⌘</span>Lihat contoh nama kolom CSV</summary>
                    <h4>Buku</h4>
                    <pre><code>judul,penulis,kategori,stok,kode_buku,penerbit,tahun_terbit</code></pre>
                    <h4>Anggota</h4>
                    <pre><code>nama,nomor_telepon,email,kelas,jurusan,alamat,tahun_masuk</code></pre>
                </details>

                <div class="import-note">
                    <span data-solar-icon="solar:lightbulb-minimalistic-linear" aria-hidden="true">!</span>
                    <p><strong>Tips:</strong> format kolom nomor telepon sebagai <strong>Teks</strong> agar angka 0 di awal tidak hilang.</p>
                </div>
            </div>
        </aside>
    </div>
</section>
@endsection
