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

    public function test_impor_buku_menolak_stok_negatif(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $csv = UploadedFile::fake()->createWithContent('books.csv', "title,author,category,stock\nBuku Rusak,Penulis,Uji,-1\n");

        $this->actingAs($staff)->from(route('imports.create'))->post(route('imports.store'), [
            'type' => 'books',
            'file' => $csv,
        ])->assertRedirect(route('imports.create'));

        $this->assertDatabaseCount('books', 0);
        $this->assertDatabaseMissing('books', ['title' => 'Buku Rusak']);
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

        $this->assertDatabaseCount('members', 1);
        $this->assertDatabaseHas('members', [
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

        $this->assertDatabaseHas('members', [
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

        $this->assertDatabaseCount('books', 0);
        $this->assertDatabaseCount('categories', 0);
    }

    public function test_impor_anggota_tidak_mewajibkan_nomor_telepon(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $csv = UploadedFile::fake()->createWithContent('anggota-tanpa-telepon.csv', "nama,email_google,kelas\nSiswa Tanpa Telepon,siswa2@example.test,XI\n");

        $this->actingAs($staff)->post(route('imports.store'), [
            'type' => 'members',
            'file' => $csv,
        ])->assertRedirect(route('imports.create'))->assertSessionHas('success');

        $this->assertDatabaseHas('members', [
            'name' => 'Siswa Tanpa Telepon',
            'email' => 'siswa2@example.test',
            'phone' => null,
        ]);
    }
}
