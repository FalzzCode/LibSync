<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ImportValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_halaman_impor_menyediakan_tautan_contoh_csv_anggota(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)
            ->get(route('imports.create'))
            ->assertOk()
            ->assertSee('contoh-anggota.csv');

        $this->assertFileExists(public_path('templates/contoh-anggota.csv'));
    }

    public function test_halaman_impor_menyediakan_tautan_contoh_csv_buku_indonesia(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)
            ->get(route('imports.create'))
            ->assertOk()
            ->assertSee('contoh-buku.csv');

        $this->assertFileExists(public_path('templates/contoh-buku.csv'));
    }

    public function test_impor_buku_menolak_stok_negatif(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $csv = UploadedFile::fake()->createWithContent('books.csv', "title,author,category,stock\nBuku Rusak,Penulis,Uji,-1\n");

        $this->actingAs($staff)->from(route('imports.create'))->post(route('imports.store'), [
            'type' => 'books',
            'file' => $csv,
        ])->assertRedirect(route('imports.create'));

        $this->assertDatabaseCount('buku', 0);
        $this->assertDatabaseMissing('buku', ['title' => 'Buku Rusak']);
    }

    public function test_impor_contoh_buku_indonesia_menyimpan_semua_baris_valid(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $csv = UploadedFile::fake()->createWithContent(
            'contoh-buku.csv',
            file_get_contents(public_path('templates/contoh-buku.csv')),
        );

        $this->actingAs($staff)->post(route('imports.store'), [
            'type' => 'books',
            'file' => $csv,
        ])->assertRedirect(route('imports.create'))
            ->assertSessionHas('success', '15 data berhasil diimpor.');

        $this->assertDatabaseCount('buku', 15);
        $this->assertDatabaseHas('buku', [
            'title' => 'Laut Bercerita',
            'author' => 'Leila S. Chudori',
            'publisher' => 'KPG',
            'publication_year' => 2025,
            'stock' => 5,
        ]);
    }

    public function test_impor_anggota_memperbarui_nis_yang_sudah_ada_tanpa_menghapus_email_lama(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $member = Member::create([
            'name' => 'Nama Lama',
            'phone' => '08123456775',
            'nis' => 'NIS-001',
            'email' => 'lama@example.test',
            'class' => 'X',
        ]);
        $csv = UploadedFile::fake()->createWithContent('members.csv', "name,phone,nis,class\nNama Baru,08123456774,NIS-001,XI\n");

        $this->actingAs($staff)->from(route('imports.create'))->post(route('imports.store'), [
            'type' => 'members',
            'file' => $csv,
        ])->assertRedirect(route('imports.create'));

        $this->assertDatabaseCount('anggota', 1);
        $this->assertDatabaseHas('anggota', [
            'id' => $member->id,
            'name' => 'Nama Baru',
            'class' => 'XI',
            'email' => 'lama@example.test',
        ]);
    }

    public function test_impor_anggota_menerima_nama_kolom_bahasa_indonesia(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $csv = UploadedFile::fake()->createWithContent('anggota.csv', "nama,nomor_telepon,email,kelas\nSiswa Indonesia,08123456776,siswa@example.test,XI\n");

        $this->actingAs($staff)->post(route('imports.store'), [
            'type' => 'members',
            'file' => $csv,
        ])->assertRedirect(route('imports.create'))->assertSessionHas('success');

        $this->assertDatabaseHas('anggota', [
            'name' => 'Siswa Indonesia',
            'phone' => '08123456776',
            'email' => 'siswa@example.test',
            'class' => 'XI',
        ]);
    }

    public function test_impor_menolak_file_dengan_terlalu_banyak_baris_secara_atomik(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $csv = UploadedFile::fake()->createWithContent(
            'too-many-rows.csv',
            "title,author,category,stock\n".str_repeat("baris-tidak-lengkap\n", 10001),
        );

        $this->actingAs($staff)->from(route('imports.create'))->post(route('imports.store'), [
            'type' => 'books',
            'file' => $csv,
        ])->assertRedirect(route('imports.create'))->assertSessionHas('error', 'File CSV terlalu besar. Maksimal 10.000 baris per impor.');

        $this->assertDatabaseCount('buku', 0);
        $this->assertDatabaseCount('kategori', 0);
    }

    public function test_impor_anggota_tidak_mewajibkan_nomor_telepon(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $csv = UploadedFile::fake()->createWithContent('anggota-tanpa-telepon.csv', "nama,email_google,kelas\nSiswa Tanpa Telepon,siswa2@example.test,XI\n");

        $this->actingAs($staff)->post(route('imports.store'), [
            'type' => 'members',
            'file' => $csv,
        ])->assertRedirect(route('imports.create'))->assertSessionHas('success');

        $this->assertDatabaseHas('anggota', [
            'name' => 'Siswa Tanpa Telepon',
            'email' => 'siswa2@example.test',
            'phone' => null,
        ]);
    }

    public function test_impor_anggota_melewati_email_yang_milik_data_arsip(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $member = Member::create([
            'name' => 'Anggota Arsip',
            'email' => 'arsip-import@example.test',
            'phone' => null,
        ]);
        $member->delete();
        $csv = UploadedFile::fake()->createWithContent('anggota-arsip.csv', "nama,email\nAnggota Baru,arsip-import@example.test\n");

        $this->actingAs($staff)->post(route('imports.store'), [
            'type' => 'members',
            'file' => $csv,
        ])->assertRedirect(route('imports.create'))
            ->assertSessionHas('success', '0 data berhasil diimpor; 1 baris dilewati karena format tidak valid.');

        $this->assertDatabaseCount('anggota', 1);
    }

    public function test_impor_anggota_tidak_menggabungkan_nama_sama_tanpa_identitas_stabil(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        Member::create(['name' => 'Nama Sama', 'phone' => null]);
        $csv = UploadedFile::fake()->createWithContent('anggota-tanpa-identitas.csv', "nama,kelas\nNama Sama,XI\n");

        $this->actingAs($staff)->post(route('imports.store'), [
            'type' => 'members',
            'file' => $csv,
        ])->assertRedirect(route('imports.create'))->assertSessionHas('success');

        $this->assertDatabaseCount('anggota', 2);
        $this->assertDatabaseHas('anggota', ['name' => 'Nama Sama', 'class' => 'XI']);
    }
}
