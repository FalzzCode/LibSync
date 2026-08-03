<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#173d3a">
    <script>try { if (sessionStorage.getItem('ruang-baca:navigation-pending')) { document.documentElement.classList.add('is-navigating'); if (window.matchMedia('(max-width: 760px)').matches) document.documentElement.classList.add('mobile-is-loading'); } } catch (_) {}</script>
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <title>{{ str_replace('Ruang Baca', 'LibSync', trim($__env->yieldContent('title', 'LibSync'))) }}</title>
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
    <link rel="stylesheet" href="{{ asset('css/responsive.css') }}">
    <link rel="stylesheet" href="{{ asset('css/experience.css') }}">
    <link rel="stylesheet" href="{{ asset('css/operations.css') }}?v=20260801-2">
    <link rel="stylesheet" href="{{ asset('css/library-imagery.css') }}?v=20260801-6">
    <link rel="stylesheet" href="{{ asset('css/sidebar-scroll-fix.css') }}">
    <link rel="stylesheet" href="{{ asset('css/collection-toolbar.css') }}?v=20260801-3">
    <link rel="stylesheet" href="{{ asset('css/mobile-viewport-fix.css') }}?v=20260801-2">
    <link rel="stylesheet" href="{{ asset('css/table-scrollbar-cleanup.css') }}?v=20260801-1">
    <link rel="stylesheet" href="{{ asset('css/members-mobile.css') }}?v=20260801-3">
    <link rel="stylesheet" href="{{ asset('css/borrowings-mobile.css') }}?v=20260801-1">
    <link rel="stylesheet" href="{{ asset('css/book-cover.css') }}?v=20260802-3">
    <link rel="stylesheet" href="{{ asset('css/borrowing-detail.css') }}?v=20260802-3">
    <link rel="stylesheet" href="{{ asset('css/profile.css') }}?v=20260802-1">
    <link rel="stylesheet" href="{{ asset('css/solar-icons.css') }}?v=20260802-3">
    <link rel="stylesheet" href="{{ asset('css/branding.css') }}?v=20260801-2">
    <link rel="stylesheet" href="{{ asset('css/student-portal.css') }}?v=20260802-1">
    <link rel="stylesheet" href="{{ asset('css/dark-mode.css') }}?v=20260802-2">
    <link rel="stylesheet" href="{{ asset('css/mobile-ux.css') }}?v=20260801-2">
    <link rel="stylesheet" href="{{ asset('css/motion-performance.css') }}?v=20260801-9">
    <link rel="stylesheet" href="{{ asset('css/action-center.css') }}?v=20260801-2">
    <link rel="stylesheet" href="{{ asset('css/circulation-dashboard.css') }}?v=20260802-3">
    <link rel="stylesheet" href="{{ asset('css/imports.css') }}?v=20260803-1">
</head>
<body>
    @php
        $roleLabels = ['admin' => 'Admin', 'staff' => 'Petugas', 'student' => 'Siswa', 'developer' => 'Pengembang'];
        $brandSubtitle = match (Auth::user()->role) {
            'student' => 'Portal siswa',
            'developer' => 'Mode pengembang',
            default => 'Manajemen perpustakaan',
        };
    @endphp
    <a href="#mainContent" class="skip-link">Langsung ke konten utama</a>
    <div class="layout">
        <div class="sidebar-overlay" id="sidebarOverlay"></div>
        <aside class="layout__sidebar" id="appSidebar" aria-label="Navigasi utama">
            <div class="sidebar__brand">
                <span class="brand-logo" aria-hidden="true"><img src="{{ asset('images/libsync-logo-512.png') }}" alt=""></span>
                <span><strong class="brand-wordmark">LibSync</strong><small>{{ $brandSubtitle }}</small></span>
                <button class="sidebar__close" id="sidebarClose" type="button" aria-label="Tutup menu">×</button>
            </div>
            <nav class="sidebar__menu">
                @if (Auth::user()->role === 'developer')
                <p class="sidebar__section-label">Alat pengembang</p>
                <a href="{{ route('developer.index') }}" @class(['sidebar__link', 'sidebar__link--active' => request()->routeIs('developer.*')])><span aria-hidden="true">⌘</span> Panel pengembang</a>
                <p class="sidebar__section-label">Akun</p>
                <a href="{{ route('profile.edit') }}" @class(['sidebar__link', 'sidebar__link--active' => request()->routeIs('profile.*')])><span aria-hidden="true">◉</span> Profil</a>
                @elseif (Auth::user()->role === 'student')
                <p class="sidebar__section-label">Portal siswa</p>
                <a href="{{ route('student.dashboard') }}" @class(['sidebar__link', 'sidebar__link--active' => request()->routeIs('student.dashboard')])><span aria-hidden="true">⌂</span> Beranda</a>
                <a href="{{ route('student.catalog') }}" @class(['sidebar__link', 'sidebar__link--active' => request()->routeIs('student.catalog')])><span aria-hidden="true">▤</span> Katalog buku</a>
                @else
                <p class="sidebar__section-label">Workspace</p>
                <a href="{{ route('dashboard') }}" @class(['sidebar__link', 'sidebar__link--active' => request()->routeIs('dashboard')])><span aria-hidden="true">⌂</span> Dashboard</a>
                <p class="sidebar__section-label">Data perpustakaan</p>
                <a href="{{ route('books.index') }}" @class(['sidebar__link', 'sidebar__link--active' => request()->routeIs('books.*')])><span aria-hidden="true">▤</span> Koleksi buku</a>
                <a href="{{ route('book-copies.index') }}" @class(['sidebar__link', 'sidebar__link--active' => request()->routeIs('book-copies.*')])><span aria-hidden="true">#</span> Eksemplar buku</a>
                <a href="{{ route('members.index') }}" @class(['sidebar__link', 'sidebar__link--active' => request()->routeIs('members.*')])><span aria-hidden="true">♙</span> Anggota</a>
                <a href="{{ route('categories.index') }}" @class(['sidebar__link', 'sidebar__link--active' => request()->routeIs('categories.*')])><span aria-hidden="true">◇</span> Kategori</a>
                <p class="sidebar__section-label">Sirkulasi</p>
                <a href="{{ route('borrowings.index') }}" @class(['sidebar__link', 'sidebar__link--active' => request()->routeIs('borrowings.*')])><span aria-hidden="true">↺</span> Transaksi</a>
                <a href="{{ route('warnings.index') }}" @class(['sidebar__link', 'sidebar__link--active' => request()->routeIs('warnings.*')])><span aria-hidden="true">!</span> Peringatan</a>
                <a href="{{ route('fines.index') }}" @class(['sidebar__link', 'sidebar__link--fines', 'sidebar__link--active' => request()->routeIs('fines.*')])><span aria-hidden="true">Rp</span> Denda</a>
                <a href="{{ route('reports.index') }}" @class(['sidebar__link', 'sidebar__link--active' => request()->routeIs('reports.*')])><span aria-hidden="true">↓</span> Laporan</a>
                <a href="{{ route('imports.create') }}" @class(['sidebar__link', 'sidebar__link--active' => request()->routeIs('imports.*')])><span aria-hidden="true">↑</span> Impor data</a>
                @if (Auth::user()->role === 'admin')
                    <p class="sidebar__section-label">Administrasi</p>
                    <a href="{{ route('users.index') }}" @class(['sidebar__link', 'sidebar__link--active' => request()->routeIs('users.*')])><span aria-hidden="true">◉</span> Pengguna</a>
                    <a href="{{ route('settings.edit') }}" @class(['sidebar__link', 'sidebar__link--active' => request()->routeIs('settings.*')])><span aria-hidden="true">⚙</span> Pengaturan</a>
                    <a href="{{ route('backups.download') }}" class="sidebar__link"><span aria-hidden="true">↓</span> Backup data</a>
                @endif
                @endif
            </nav>
            <div class="sidebar__footer"><span class="status-dot"></span> Sistem aktif</div>
        </aside>
        <div class="layout__main">
            <header class="header">
                <button class="header__menu-toggle" id="menuToggle" type="button" aria-label="Buka menu" aria-controls="appSidebar" aria-expanded="false">☰</button>
                <div class="header__context"><span>LibSync · perpustakaan digital</span><strong>@yield('eyebrow', 'Ringkasan operasional')</strong></div>
                <div class="header__right">
                    @if (app()->environment('local') && (Auth::user()->role === 'developer' || session()->has('developer_original_user_id')))
                        <a class="developer-mode-link" href="{{ route('developer.index') }}">Pengembang</a>
                    @endif
                    <button id="themeToggle" class="theme-toggle" type="button" aria-label="Aktifkan mode gelap" title="Ganti tema"><span aria-hidden="true">◐</span></button>
                <div class="header__profile-wrap">
                    <button class="header__profile" id="profileToggle" type="button" aria-expanded="false" aria-haspopup="menu" aria-controls="profileDropdown">
                        @if (Auth::user()->profile_photo_path)<img class="avatar" src="{{ route('profile.photo', Auth::user()) }}?v={{ Auth::user()->updated_at?->timestamp }}" alt="">@elseif(Auth::user()->avatar_url)<img class="avatar" src="{{ Auth::user()->avatar_url }}" alt="">@else<span class="avatar" aria-hidden="true">{{ strtoupper(substr(Auth::user()->name, 0, 1)) }}</span>@endif
                        <span class="header__profile-info"><strong>{{ Auth::user()->name }}</strong><small>{{ $roleLabels[Auth::user()->role] ?? ucfirst(Auth::user()->role) }}</small></span><span aria-hidden="true">⌄</span>
                    </button>
                    <div class="dropdown-menu" id="profileDropdown" role="menu">
                        <p>Masuk sebagai <strong>{{ Auth::user()->email }}</strong></p>
                        <a class="dropdown-menu__profile" href="{{ route('profile.edit') }}">Pengaturan profil <span>→</span></a>
                        <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="dropdown-menu__logout">Keluar dari akun <span>→</span></button></form>
                    </div>
                </div>
                </div>
            </header>
            @if (session('success'))<div class="alert alert--success" role="status"><span>✓</span>{{ session('success') }}</div>@endif
            @if (session('error'))<div class="alert alert--error" role="alert"><span>!</span>{{ session('error') }}</div>@endif
            @if ($errors->any())<div class="alert alert--error alert--validation" role="alert"><span>!</span><div><strong>Data belum dapat disimpan.</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>@endif
            <main id="mainContent" class="main-content">@yield('content')</main>
        </div>
    </div>
    <nav @class(['mobile-tabbar', 'mobile-tabbar--student' => Auth::user()->role === 'student']) aria-label="Navigasi cepat">
        @if (Auth::user()->role === 'student')
            <a href="{{ route('student.dashboard') }}" @class(['is-active' => request()->routeIs('student.dashboard')])><span aria-hidden="true">⌂</span>Beranda</a>
            <a href="{{ route('student.catalog') }}" @class(['is-active' => request()->routeIs('student.catalog')])><span aria-hidden="true">▦</span>Katalog</a>
        @else
            <a href="{{ route('dashboard') }}" @class(['is-active' => request()->routeIs('dashboard')])><span aria-hidden="true">⌂</span>Beranda</a>
            <a href="{{ route('books.index') }}" @class(['is-active' => request()->routeIs('books.*')])><span aria-hidden="true">▤</span>Koleksi</a>
            <a href="{{ route('borrowings.index') }}" @class(['is-active' => request()->routeIs('borrowings.*')])><span aria-hidden="true">↻</span>Transaksi</a>
        @endif
        <a href="{{ route('profile.edit') }}" @class(['is-active' => request()->routeIs('profile.*')])><span aria-hidden="true">◉</span>Profil</a>
    </nav>
    <div class="modal-overlay" id="modalOverlay" hidden aria-hidden="true"><section class="modal" role="alertdialog" aria-modal="true" aria-labelledby="modalTitle" aria-describedby="modalMessage"><h2 id="modalTitle">Konfirmasi tindakan</h2><p id="modalMessage"></p><div class="modal__actions"><button class="btn btn--secondary" id="modalCancel" type="button">Batal</button><button class="btn btn--danger" id="modalConfirm" type="button">Hapus</button></div></section></div>
    @php
        $loaderType = match (true) {
            request()->routeIs('dashboard', 'student.dashboard') => 'dashboard',
            request()->routeIs('books.index') => 'books',
            request()->routeIs('book-copies.index') => 'copies',
            request()->routeIs('members.index') => 'members',
            request()->routeIs('categories.index') => 'categories',
            request()->routeIs('borrowings.index') => 'borrowings',
            request()->routeIs('warnings.index') => 'warnings',
            request()->routeIs('fines.index') => 'fines',
            request()->routeIs('imports.create') => 'imports',
            request()->routeIs('users.index') => 'users',
            request()->routeIs('developer.*') => 'developer',
            request()->routeIs('*.create', '*.edit') => 'form',
            request()->routeIs('books.show', 'borrowings.show') => 'detail',
            request()->routeIs('student.catalog') => 'catalog',
            default => 'table',
        };
    @endphp
    <div class="page-loader page-loader--{{ $loaderType }}" id="pageLoader" hidden role="status" aria-live="polite" aria-label="Memuat halaman">
        <div class="page-loader__content">
            <div class="page-loader__main" aria-hidden="true">
                <div class="page-loader__label"></div><div class="page-loader__title"></div>
                <section class="page-loader__filters"><i></i><b></b><b></b></section>
                <section class="page-loader__table"><header><i></i><i></i><i></i><i></i></header><div><i></i><i></i><i></i><i></i></div><div><i></i><i></i><i></i><i></i></div><div><i></i><i></i><i></i><i></i></div><div><i></i><i></i><i></i><i></i></div></section>
                <section class="page-loader__dashboard"><div class="page-loader__hero"><div></div><div></div><div></div></div><div class="page-loader__stats"><i></i><i></i><i></i><i></i></div><div class="page-loader__panel"><i></i><i></i></div></section>
                <section class="page-loader__form"><div class="page-loader__form-head"></div><div class="page-loader__form-grid"><i></i><i></i><i></i><i></i><i></i><i></i></div><div class="page-loader__form-actions"><i></i><i></i></div></section>
                <section class="page-loader__detail"><i></i><div><b></b><b></b><b></b><b></b><b></b></div></section>
                <section class="page-loader__catalog"><i></i><i></i><i></i><i></i></section>
                @foreach (['books','copies','members','categories','borrowings','warnings','fines','imports','users','developer'] as $loaderVariant)
                    <section class="page-loader__route page-loader__route--{{ $loaderVariant }}">
                        @switch($loaderVariant)
                            @case('books')
                                <div class="loader-books__toolbar"><i></i><b></b><b></b></div><div class="loader-books__rows"><div><i></i><b></b><b></b><b></b><em></em></div><div><i></i><b></b><b></b><b></b><em></em></div><div><i></i><b></b><b></b><b></b><em></em></div></div>
                                @break
                            @case('copies')
                                <div class="loader-copies__scan"><i></i><b></b></div><div class="loader-copies__rows"><i></i><i></i><i></i><i></i></div>
                                @break
                            @case('members')
                                <div class="loader-members__head"><i></i><b></b></div><div class="loader-members__rows"><div><i></i><b></b><b></b><em></em></div><div><i></i><b></b><b></b><em></em></div><div><i></i><b></b><b></b><em></em></div><div><i></i><b></b><b></b><em></em></div></div>
                                @break
                            @case('categories')
                                <div class="loader-categories__grid"><i></i><i></i><i></i><i></i><i></i><i></i></div>
                                @break
                            @case('borrowings')
                                <div class="loader-borrowings__stats"><i></i><i></i><i></i></div><div class="loader-borrowings__timeline"><b></b><b></b><b></b><b></b></div>
                                @break
                            @case('warnings')
                                <div class="loader-warnings__list"><div><i></i><b></b><em></em></div><div><i></i><b></b><em></em></div><div><i></i><b></b><em></em></div></div>
                                @break
                            @case('fines')
                                <div class="loader-fines__summary"><i></i><i></i><i></i></div><div class="loader-fines__rows"><b></b><b></b><b></b><b></b></div>
                                @break
                            @case('imports')
                                <div class="loader-imports__dropzone"><i>↑</i><b></b><b></b></div><div class="loader-imports__guide"><i></i><i></i></div>
                                @break
                            @case('users')
                                <div class="loader-users__grid"><div><i></i><b></b><b></b></div><div><i></i><b></b><b></b></div><div><i></i><b></b><b></b></div></div>
                                @break
                            @case('developer')
                                <div class="loader-developer__console"><i></i><b></b><b></b><b></b><b></b></div>
                        @endswitch
                    </section>
                @endforeach
            </div>
            <p>Menyiapkan halaman…</p>
        </div>
    </div>
    <script src="https://code.iconify.design/iconify-icon/2.3.0/iconify-icon.min.js" defer></script>
    <script src="{{ asset('js/script.js') }}?v=20260802-2" defer></script>
    @vite('resources/js/app.js')
    <script>if ('serviceWorker' in navigator) { navigator.serviceWorker.register('{{ asset('service-worker.js') }}?v=20260802-4'); }</script>
</body>
</html>
