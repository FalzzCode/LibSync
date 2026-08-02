<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookReservation;
use App\Models\Borrowing;
use App\Models\Category;
use App\Models\Fine;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StudentPortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_siswa_hanya_bisa_membuka_portal_miliknya(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        Member::create(['user_id' => $student->id, 'name' => 'Siswa Portal', 'phone' => '08123456789']);

        $this->actingAs($student)->get(route('student.dashboard'))->assertOk()->assertSee('Siswa Portal')->assertSee('Portal siswa');
        $this->actingAs($student)->get(route('books.index'))->assertForbidden();
    }

    public function test_petugas_dapat_menyimpan_anggota_dengan_akun_portal(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)->post(route('members.store'), [
            'name' => 'Anggota Baru',
            'phone' => '08123456788',
            'nis' => '2026001',
            'account_email' => 'anggota@example.test',
            'account_password' => 'rahasia123',
        ])->assertRedirect(route('members.index'));

        $member = Member::where('nis', '2026001')->firstOrFail();
        $this->assertNotNull($member->user_id);
        $this->assertDatabaseHas('users', ['id' => $member->user_id, 'email' => 'anggota@example.test', 'role' => 'student']);
    }

    public function test_petugas_dapat_menyimpan_anggota_tanpa_akun_portal(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)->post(route('members.store'), [
            'name' => 'Anggota Tanpa Akun',
            'phone' => '08123456787',
        ])->assertRedirect(route('members.index'));

        $this->assertDatabaseHas('members', ['name' => 'Anggota Tanpa Akun', 'phone' => '08123456787']);
    }

    public function test_petugas_dapat_mengatur_ulang_password_akun_siswa_dari_data_anggota(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $student = User::factory()->create([
            'role' => 'student',
            'email' => 'naufal@example.test',
            'password' => Hash::make('password-lama'),
        ]);
        $member = Member::create([
            'user_id' => $student->id,
            'name' => 'Naufal',
            'phone' => '08123456789',
        ]);

        $this->actingAs($staff)->put(route('members.update', $member), [
            'name' => 'Naufal',
            'phone' => '08123456789',
            'account_email' => 'naufal@example.test',
            'account_password' => 'password-baru',
        ])->assertRedirect(route('members.index'));

        $this->assertTrue(Hash::check('password-baru', $student->fresh()->password));
    }

    public function test_pengembalian_buku_memberi_notifikasi_kepada_antrean_berikutnya(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $student = User::factory()->create(['role' => 'student']);
        $member = Member::create(['user_id' => $student->id, 'name' => 'Siswa Antrean', 'phone' => '08123456780']);
        $category = Category::create(['name' => 'Fiksi']);
        $book = Book::create(['title' => 'Buku Antrean', 'author' => 'Penulis', 'category_id' => $category->id, 'stock' => 0]);
        $borrowingMember = Member::create(['name' => 'Peminjam', 'phone' => '08123456781']);
        $borrowing = Borrowing::create(['member_id' => $borrowingMember->id, 'book_id' => $book->id, 'user_id' => $staff->id, 'borrowed_at' => today()->subDays(3), 'due_date' => today()->addDays(4), 'status' => 'borrowed']);
        $reservation = BookReservation::create(['book_id' => $book->id, 'member_id' => $member->id, 'status' => 'waiting', 'queue_position' => 1]);

        $this->actingAs($staff)->post(route('borrowings.return', $borrowing), ['returned_at' => today()->toDateString()])->assertRedirect();

        $this->assertDatabaseHas('book_reservations', ['id' => $reservation->id, 'status' => 'ready']);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $student->id]);
    }

    public function test_siswa_dapat_masuk_daftar_tunggu_saat_buku_habis(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $member = Member::create(['user_id' => $student->id, 'name' => 'Siswa Daftar Tunggu', 'phone' => '08123456782']);
        $book = Book::create(['title' => 'Buku Habis', 'author' => 'Penulis', 'category_id' => Category::create(['name' => 'Sains'])->id, 'stock' => 0]);

        $this->actingAs($student)->post(route('student.reservations.store', $book))->assertRedirect();

        $this->assertDatabaseHas('book_reservations', ['book_id' => $book->id, 'member_id' => $member->id, 'status' => 'waiting', 'queue_position' => 1]);
    }

    public function test_siswa_tidak_bisa_menambahkan_buku_yang_masih_tersedia_ke_daftar_tunggu(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        Member::create(['user_id' => $student->id, 'name' => 'Siswa Buku Tersedia', 'phone' => '08123456778']);
        $book = Book::create(['title' => 'Buku Ada', 'author' => 'Penulis', 'category_id' => Category::create(['name' => 'Sejarah'])->id, 'stock' => 1]);

        $this->actingAs($student)->from(route('student.catalog'))->post(route('student.reservations.store', $book))
            ->assertRedirect(route('student.catalog'));

        $this->assertDatabaseCount('book_reservations', 0);
    }

    public function test_siswa_dengan_denda_belum_lunas_tidak_bisa_mengajukan_peminjaman(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $member = Member::create(['user_id' => $student->id, 'name' => 'Siswa Berdenda', 'phone' => '08123456768']);
        $book = Book::create(['title' => 'Buku Denda', 'author' => 'Penulis', 'category_id' => Category::create(['name' => 'Ekonomi'])->id, 'stock' => 1]);
        Fine::create(['member_id' => $member->id, 'amount' => 5000, 'type' => 'late', 'status' => 'unpaid']);

        $this->actingAs($student)->from(route('student.catalog'))->post(route('student.borrowings.store', $book))
            ->assertRedirect(route('student.catalog'))
            ->assertSessionHasErrors('member_id');

        $this->assertDatabaseCount('borrowings', 0);
    }

    public function test_hanya_admin_dapat_mengunduh_snapshot_backup(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($staff)->get(route('backups.download'))->assertForbidden();
        $response = $this->actingAs($admin)->get(route('backups.download'));
        $response->assertOk()
            ->assertHeader('content-type', 'application/json; charset=UTF-8')
            ->assertHeader('cache-control', 'no-store, private');
        ob_start();
        $response->sendContent();
        $body = (string) ob_get_clean();
        $snapshot = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('ruang-baca-data-snapshot', $snapshot['format']);
        $this->assertArrayHasKey('users', $snapshot['data']);
        $this->assertStringNotContainsString('password', $body);
    }
}
