<div class="form-card__header">
    <div><h2>Profil anggota</h2><p>Informasi ini digunakan sebagai data utama anggota perpustakaan.</p></div>
</div>
<div class="form-grid">
    <div class="form-group"><label for="name">Nama lengkap <span>*</span></label><input id="name" name="name" value="{{ old('name', $member?->name) }}" required>@error('name')<small class="field-error">{{ $message }}</small>@enderror</div>
    <div class="form-group"><label for="class">Kelas</label><input id="class" name="class" value="{{ old('class', $member?->class) }}"></div>
    <div class="form-group"><label for="major">Jurusan</label><input id="major" name="major" value="{{ old('major', $member?->major) }}"></div>
    <div class="form-group"><label for="phone">Nomor telepon <span class="field-optional">(opsional)</span></label><input id="phone" name="phone" type="tel" value="{{ old('phone', $member?->phone) }}" autocomplete="tel">@error('phone')<small class="field-error">{{ $message }}</small>@enderror</div>
    <div class="form-group"><label for="email">Email Google <span class="field-optional">(disarankan)</span></label><input id="email" name="email" type="email" value="{{ old('email', $member?->email) }}" autocomplete="email"><small class="field-hint">Dipakai untuk menghubungkan akun Google siswa.</small>@error('email')<small class="field-error">{{ $message }}</small>@enderror</div>
    <div class="form-group"><label for="entry_year">Tahun masuk</label><input id="entry_year" name="entry_year" type="number" min="1900" max="{{ now()->year }}" value="{{ old('entry_year', $member?->entry_year) }}"><small class="field-hint">Masukkan tahun antara 1900 dan {{ now()->year }}.</small>@error('entry_year')<small class="field-error">{{ $message }}</small>@enderror</div>
    <div class="form-group form-group--wide"><label for="address">Alamat</label><textarea id="address" name="address" rows="3">{{ old('address', $member?->address) }}</textarea></div>
</div>
@if (config('auth.local_login_enabled'))
<div class="form-card__header">
    <div><h2>Akun login lokal</h2><p>Opsional untuk pengujian di komputer lokal. Kosongkan password saat edit jika tidak ingin mengubahnya.</p></div>
</div>
<div class="form-grid">
    <div class="form-group"><label for="account_email">Email login</label><input id="account_email" name="account_email" type="email" value="{{ old('account_email', $member?->user?->email) }}" autocomplete="username">@error('account_email')<small class="field-error">{{ $message }}</small>@enderror</div>
    <div class="form-group"><label for="account_password">Password login {{ $member?->user ? '(opsional)' : '' }}</label><input id="account_password" name="account_password" type="password" minlength="8" autocomplete="new-password"><small class="field-hint">Minimal 8 karakter. Password tidak pernah ditampilkan kembali.</small>@error('account_password')<small class="field-error">{{ $message }}</small>@enderror</div>
</div>
@endif
<div class="form-actions"><a class="btn btn--secondary" href="{{ route('members.index') }}">Batal</a><button class="btn btn--primary" type="submit">{{ $submitLabel }}</button></div>
