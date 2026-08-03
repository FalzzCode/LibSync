# Flowchart LibSync

Dokumen ini menggambarkan alur utama project LibSync dari login sampai
operasional perpustakaan dan pemasangan domain.

![Flowchart LibSync detail](artifacts/flowchart-libsync-detailed.png)

## 1. Alur pengguna dan operasional

```mermaid
flowchart LR
    start([Buka LibSync]) --> login[Halaman login]

    subgraph identity ["Login dan identitas"]
        google[Login dengan Google]
        provider[Google OAuth]
        callbackOk{Callback berhasil?}
        retry[Pesan gagal dan coba lagi]
        local[Login lokal sementara]
        localCheck[Validasi email dan password]
        localOk{Kredensial valid?}
        legacy[Aktivasi data lama dengan NIS]
        role{Peran akun?}
        studentLink[Profil siswa dibuat atau dihubungkan via email]
    end

    login --> google
    google --> provider
    provider --> callbackOk
    callbackOk -->|"Tidak"| retry
    retry --> login
    callbackOk -->|"Ya"| role
    login -.->|"Google tidak tersedia"| local
    local --> localCheck
    localCheck --> localOk
    localOk -->|"Tidak"| login
    localOk -->|"Ya"| role
    login -.->|"Data lama"| legacy
    legacy --> provider
    role -->|"Siswa"| studentLink

    subgraph adminFlow ["Alur admin"]
        adminDash[Dashboard admin]
        adminManage[Kelola koleksi anggota dan kategori]
        adminUsers[Kelola pengguna dan pengaturan]
        adminReports[Laporan dan backup]
        adminDash --> adminManage
        adminDash --> adminUsers
        adminDash --> adminReports
    end

    subgraph staffFlow ["Alur petugas"]
        staffDash[Dashboard petugas]
        staffManage[Kelola buku anggota dan impor CSV]
        circulation[Proses sirkulasi denda dan peringatan]
        staffReview[Tinjau permintaan siswa]
        staffDash --> staffManage
        staffDash --> circulation
        staffDash --> staffReview
    end

    subgraph studentFlow ["Alur siswa"]
        studentDash[Portal siswa]
        catalog[Katalog dan pencarian]
        availability{Buku tersedia?}
        borrow[Ajukan peminjaman]
        waitlist[Masuk daftar tunggu]
        request[Ajukan perpanjangan atau pengembalian]
        studentDash --> catalog
        catalog --> availability
        availability -->|"Ya"| borrow
        availability -->|"Tidak"| waitlist
        studentDash --> request
    end

    role -->|"Admin"| adminDash
    role -->|"Petugas"| staffDash
    studentLink --> studentDash
    request --> staffReview

    mysql[(Database perpustakaan)]
    files[(Backup dan foto privat)]
    scheduler[Scheduler otomatis]
    overdue[Cek keterlambatan dan reservasi]

    adminManage --> mysql
    adminUsers --> mysql
    adminReports --> files
    staffManage --> mysql
    circulation --> mysql
    staffReview --> mysql
    borrow --> mysql
    waitlist --> mysql
    scheduler --> overdue
    overdue --> mysql

    style identity fill:#C6FAF6,stroke:#5AD8CC
    style adminFlow fill:#DCCCFF,stroke:#874FFF
    style staffFlow fill:#C2E5FF,stroke:#3DADFF
    style studentFlow fill:#CDF4D3,stroke:#66D575
    style callbackOk fill:#FFECBD,stroke:#FFC943
    style localOk fill:#FFECBD,stroke:#FFC943
    style availability fill:#FFECBD,stroke:#FFC943
    style retry fill:#FFCDC2,stroke:#FF7556
    style mysql fill:#D9D9D9,stroke:#B3B3B3
    style files fill:#D9D9D9,stroke:#B3B3B3
```

## 2. Alur deployment dan runtime

```mermaid
flowchart LR
    source[/Source code dan GitHub/] --> ci[CI: test dan build]
    ci --> artifact[/Project dan public/build/]
    artifact --> hosting[Hosting PHP Laravel]

    hosting --> env[Isi .env produksi]
    env --> preflight{Preflight tanpa GAGAL?}
    preflight -->|"Tidak"| env
    preflight -->|"Ya"| domain[Domain dan HTTPS]

    hosting --> migrate[Jalankan migrate dan seed]
    migrate --> database[(MySQL produksi)]
    hosting --> storage[(Storage foto dan backup)]
    hosting -.-> oauth[Google Cloud OAuth]
    cron[ cron setiap menit ] --> hosting
    hosting --> admin[Create admin pertama]
    hosting --> health[/GET /up mengembalikan 200/]

    style preflight fill:#FFECBD,stroke:#FFC943
    style domain fill:#CDF4D3,stroke:#66D575
    style database fill:#D9D9D9,stroke:#B3B3B3
    style storage fill:#D9D9D9,stroke:#B3B3B3
    style oauth fill:#C6FAF6,stroke:#5AD8CC
```

## Ringkasan hak akses

| Peran | Akses utama | Batasan |
| --- | --- | --- |
| Admin | Semua modul, pengguna, pengaturan, backup | Tidak ada batasan modul operasional |
| Petugas | Buku, eksemplar, anggota, impor, sirkulasi, denda, laporan | Tidak dapat mengelola admin, petugas, pengaturan, atau backup |
| Siswa | Katalog, peminjaman, daftar tunggu, perpanjangan, pengembalian | Tidak dapat membuka modul administrasi |

## Catatan identitas siswa

Login Google biasa tidak membutuhkan NIS. Sistem menggunakan email Google untuk
membuat atau menghubungkan profil siswa. NIS hanya dipakai pada alur aktivasi
data lama yang memang sudah memiliki kode aktivasi.
