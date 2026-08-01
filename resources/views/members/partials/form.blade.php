<div class="form-card__header">
    <div><h2>Profil anggota</h2><p>Informasi ini digunakan sebagai data utama anggota perpustakaan.</p></div>
</div>
<div class="form-grid">
    <div class="form-group"><label for="name">Nama lengkap <span>*</span></label><input id="name" name="name" value="{{ old('name', $member?->name) }}" required>@error('name')<small class="field-error">{{ $message }}</small>@enderror</div>
    <div class="form-group"><label for="nis">NIS <span>*</span></label><input id="nis" name="nis" value="{{ old('nis', $member?->nis) }}" required>@error('nis')<small class="field-error">{{ $message }}</small>@enderror</div>
    <div class="form-group"><label for="nisn">NISN</label><input id="nisn" name="nisn" value="{{ old('nisn', $member?->nisn) }}">@error('nisn')<small class="field-error">{{ $message }}</small>@enderror</div>
    <div class="form-group"><label for="class">Kelas</label><input id="class" name="class" value="{{ old('class', $member?->class) }}"></div>
    <div class="form-group"><label for="major">Jurusan</label><input id="major" name="major" value="{{ old('major', $member?->major) }}"></div>
    <div class="form-group"><label for="phone">Nomor telepon <span>*</span></label><input id="phone" name="phone" type="tel" value="{{ old('phone', $member?->phone) }}" required>@error('phone')<small class="field-error">{{ $message }}</small>@enderror</div>
    <div class="form-group"><label for="email">Email kontak</label><input id="email" name="email" type="email" value="{{ old('email', $member?->email) }}">@error('email')<small class="field-error">{{ $message }}</small>@enderror</div>
    <div class="form-group"><label for="entry_year">Tahun masuk</label><input id="entry_year" name="entry_year" type="number" min="1900" max="{{ now()->year }}" value="{{ old('entry_year', $member?->entry_year) }}"><small class="field-hint">Masukkan tahun antara 1900 dan {{ now()->year }}.</small>@error('entry_year')<small class="field-error">{{ $message }}</small>@enderror</div>
    <div class="form-group form-group--wide"><label for="address">Alamat</label><textarea id="address" name="address" rows="3">{{ old('address', $member?->address) }}</textarea></div>
</div>
<div class="form-card__header">
    <div><h2>Aktivasi portal siswa</h2><p>Setelah data disimpan, LibSync membuat kode aktivasi untuk menghubungkan akun Google siswa. Petugas tidak perlu membuat email atau password siswa.</p></div>
</div>
<div class="form-actions"><a class="btn btn--secondary" href="{{ route('members.index') }}">Batal</a><button class="btn btn--primary" type="submit">{{ $submitLabel }}</button></div>
