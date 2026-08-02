@extends('layouts.app')

@section('title', 'Profil · Ruang Baca')
@section('eyebrow', 'Pengaturan profil')

@section('content')
<section class="page profile-page">
    <div class="page-header">
        <div>
            <p class="eyebrow">Akun Anda</p>
            <h1>Profil & keamanan</h1>
            <p>Perbarui identitas akun, foto profil, alamat email, dan password Anda.</p>
        </div>
    </div>

    <div class="profile-layout">
        <aside class="profile-summary">
            <div class="profile-avatar profile-avatar--large">
                @if ($user->profile_photo_path)
                    <img src="{{ route('profile.photo', $user) }}?v={{ $user->updated_at?->timestamp }}" alt="Foto profil {{ $user->name }}">
                @elseif ($user->avatar_url)
                    <img src="{{ $user->avatar_url }}" alt="Foto profil {{ $user->name }}">
                @else
                    <span>{{ strtoupper(substr($user->name, 0, 1)) }}</span>
                @endif
            </div>
            <h2>{{ $user->name }}</h2>
            <p>{{ $user->email }}</p>
            <span class="role-badge">{{ ucfirst($user->role) }}</span>
            @if ($user->google_id)<small class="profile-summary__note">Akun Google terhubung</small>@endif
        </aside>

        <div class="profile-sections">
            <form class="form-card profile-card" method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" data-profile-photo-form>
                @csrf @method('PATCH')
                <div class="form-card__header"><h2>Informasi profil</h2><p>Nama dan email ini digunakan untuk mengenali akun Anda di sistem.</p></div>
                <div class="form-grid">
                    <div class="form-group form-group--wide">
                        <label for="photo">Foto profil</label>
                        <div class="profile-photo-field" data-profile-photo-field>
                            <input id="photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp" data-profile-photo-input>
                            <label for="photo"><span>↑</span><strong data-profile-photo-label>Pilih foto baru</strong><small data-profile-photo-status>JPG, PNG, atau WEBP · maksimal 2 MB</small></label>
                        </div>
                        @error('photo')<small class="field-error">{{ $message }}</small>@enderror
                    </div>
                    <div class="form-group"><label for="name">Nama lengkap <span>*</span></label><input id="name" name="name" value="{{ old('name', $user->name) }}" required @class(['is-invalid' => $errors->has('name')])>@error('name')<small class="field-error">{{ $message }}</small>@enderror</div>
                    <div class="form-group"><label for="email">Email akun <span>*</span></label><input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required @class(['is-invalid' => $errors->has('email')])>@error('email')<small class="field-error">{{ $message }}</small>@enderror</div>
                </div>
                <div class="form-actions"><button class="btn btn--primary" type="submit">Simpan profil</button></div>
            </form>

            <form class="form-card profile-card" method="POST" action="{{ route('profile.password') }}">
                @csrf @method('PATCH')
                <div class="form-card__header"><h2>Ganti password</h2><p>Gunakan password baru minimal 8 karakter dan jangan bagikan kepada siapa pun.</p></div>
                <div class="form-grid">
                    <div class="form-group form-group--wide"><label for="current_password">Password saat ini <span>*</span></label><input id="current_password" name="current_password" type="password" autocomplete="current-password" required @class(['is-invalid' => $errors->has('current_password')])>@error('current_password')<small class="field-error">{{ $message }}</small>@enderror</div>
                    <div class="form-group"><label for="password">Password baru <span>*</span></label><input id="password" name="password" type="password" autocomplete="new-password" required @class(['is-invalid' => $errors->has('password')])>@error('password')<small class="field-error">{{ $message }}</small>@enderror</div>
                    <div class="form-group"><label for="password_confirmation">Ulangi password baru <span>*</span></label><input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required></div>
                </div>
                <div class="form-actions"><button class="btn btn--secondary" type="submit">Perbarui password</button></div>
            </form>
        </div>
    </div>
    <div class="modal-overlay profile-photo-confirm-overlay" data-profile-photo-confirm hidden aria-hidden="true">
        <section class="modal profile-photo-confirm" role="dialog" aria-modal="true" aria-labelledby="profilePhotoConfirmTitle" aria-describedby="profilePhotoConfirmMessage">
            <div class="profile-photo-confirm__preview"><img data-profile-photo-confirm-preview alt="Pratinjau foto profil"></div>
            <h2 id="profilePhotoConfirmTitle">Gunakan foto profil ini?</h2>
            <p id="profilePhotoConfirmMessage" data-profile-photo-confirm-message>Foto baru akan disimpan saat Anda menekan “Simpan profil”.</p>
            <div class="modal__actions"><button class="btn btn--secondary" type="button" data-profile-photo-cancel>Ganti foto</button><button class="btn btn--primary" type="button" data-profile-photo-accept>Gunakan foto</button></div>
        </section>
    </div>
</section>
@endsection
