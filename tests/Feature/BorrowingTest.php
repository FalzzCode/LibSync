<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookReservation;
use App\Models\Borrowing;
use App\Models\Category;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BorrowingTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create(['role' => 'staff']);
    }

    private function book(int $stock = 3): Book
    {
        return Book::create([
            'title' => 'Buku Pengujian', 'author' => 'Penulis', 'stock' => $stock,
            'category_id' => Category::firstOrCreate(['name' => 'Umum'])->id,
        ]);
    }

    public function test_peminjaman_mengurangi_stok_dan_mencatat_petugas(): void
    {
        $user = $this->user();
        $book = $this->book();
        $member = Member::create(['name' => 'Andi', 'phone' => '08123456789']);

        $this->actingAs($user)->post(route('borrowings.store'), [
            'member_id' => $member->id, 'book_id' => $book->id,
            'borrowed_at' => '2026-07-01', 'due_date' => '2026-07-08',
        ])->assertRedirect(route('borrowings.index'));

        $this->assertDatabaseHas('borrowings', ['member_id' => $member->id, 'book_id' => $book->id, 'user_id' => $user->id, 'status' => 'borrowed']);
        $this->assertSame(2, $book->fresh()->stock);
    }

    public function test_peminjaman_ditolak_saat_stok_habis(): void
    {
        $book = $this->book(0);
        $member = Member::create(['name' => 'Budi', 'phone' => '08123456780']);

        $this->actingAs($this->user())->from(route('borrowings.create'))->post(route('borrowings.store'), [
            'member_id' => $member->id, 'book_id' => $book->id,
            'borrowed_at' => '2026-07-01', 'due_date' => '2026-07-08',
        ])->assertRedirect(route('borrowings.create'))->assertSessionHasErrors('book_id');

        $this->assertDatabaseCount('borrowings', 0);
        $this->assertSame(0, $book->fresh()->stock);
    }

    public function test_peminjaman_ditolak_untuk_buku_yang_diarsipkan(): void
    {
        $book = $this->book();
        $book->update(['archived_at' => now()]);
        $member = Member::create(['name' => 'Buku Arsip', 'phone' => '08123456779']);

        $this->actingAs($this->user())->from(route('borrowings.create'))->post(route('borrowings.store'), [
            'member_id' => $member->id, 'book_id' => $book->id,
            'borrowed_at' => '2026-07-01', 'due_date' => '2026-07-08',
        ])->assertRedirect(route('borrowings.create'))->assertSessionHasErrors('book_id');

        $this->assertDatabaseCount('borrowings', 0);
        $this->assertSame(3, $book->fresh()->stock);
    }

    public function test_buku_yang_siap_diambil_hanya_dapat_dipinjam_oleh_pemilik_reservasi(): void
    {
        $staff = $this->user();
        $book = $this->book(1);
        $priorityMember = Member::create(['name' => 'Pemilik Antrean', 'phone' => '08123456777']);
        $otherMember = Member::create(['name' => 'Peminjam Lain', 'phone' => '08123456776']);
        $reservation = BookReservation::create([
            'book_id' => $book->id,
            'member_id' => $priorityMember->id,
            'status' => 'ready',
            'queue_position' => 1,
            'expires_at' => now()->addDays(3),
        ]);

        $this->actingAs($staff)->from(route('borrowings.create'))->post(route('borrowings.store'), [
            'member_id' => $otherMember->id, 'book_id' => $book->id,
            'borrowed_at' => '2026-07-01', 'due_date' => '2026-07-08',
        ])->assertRedirect(route('borrowings.create'))->assertSessionHasErrors('book_id');

        $this->assertDatabaseCount('borrowings', 0);
        $this->assertDatabaseHas('book_reservations', ['id' => $reservation->id, 'status' => 'ready']);

        $this->actingAs($staff)->post(route('borrowings.store'), [
            'member_id' => $priorityMember->id, 'book_id' => $book->id,
            'borrowed_at' => '2026-07-01', 'due_date' => '2026-07-08',
        ])->assertRedirect(route('borrowings.index'));

        $this->assertDatabaseHas('book_reservations', ['id' => $reservation->id, 'status' => 'fulfilled']);
        $this->assertSame(0, $book->fresh()->stock);
    }

    public function test_setiap_pemilik_reservasi_siap_dapat_meminjam_saat_ada_beberapa_stok(): void
    {
        $staff = $this->user();
        $book = $this->book(2);
        $firstMember = Member::create(['name' => 'Antrean Pertama', 'phone' => '08123456774']);
        $secondMember = Member::create(['name' => 'Antrean Kedua', 'phone' => '08123456773']);
        $firstReservation = BookReservation::create(['book_id' => $book->id, 'member_id' => $firstMember->id, 'status' => 'ready', 'queue_position' => 1, 'expires_at' => now()->addDays(3)]);
        $secondReservation = BookReservation::create(['book_id' => $book->id, 'member_id' => $secondMember->id, 'status' => 'ready', 'queue_position' => 2, 'expires_at' => now()->addDays(3)]);

        $this->actingAs($staff)->post(route('borrowings.store'), [
            'member_id' => $secondMember->id, 'book_id' => $book->id,
            'borrowed_at' => '2026-07-01', 'due_date' => '2026-07-08',
        ])->assertRedirect(route('borrowings.index'));

        $this->assertDatabaseHas('book_reservations', ['id' => $firstReservation->id, 'status' => 'ready']);
        $this->assertDatabaseHas('book_reservations', ['id' => $secondReservation->id, 'status' => 'fulfilled']);
        $this->assertSame(1, $book->fresh()->stock);
    }

    public function test_pengembalian_menambah_stok_dan_menghitung_denda(): void
    {
        $user = $this->user();
        $book = $this->book(1);
        $member = Member::create(['name' => 'Caca', 'phone' => '08123456781']);
        $borrowing = Borrowing::create(['member_id' => $member->id, 'book_id' => $book->id, 'user_id' => $user->id, 'borrowed_at' => '2026-07-01', 'due_date' => '2026-07-08', 'status' => 'borrowed']);

        $this->actingAs($user)->post(route('borrowings.return', $borrowing), ['returned_at' => '2026-07-11'])
            ->assertRedirect(route('borrowings.show', $borrowing));

        $this->assertDatabaseHas('borrowings', ['id' => $borrowing->id, 'status' => 'returned', 'fine' => 3000, 'returned_at' => '2026-07-11 00:00:00']);
        $this->assertSame(2, $book->fresh()->stock);
    }

    public function test_pengembalian_ganda_ditolak(): void
    {
        $user = $this->user();
        $book = $this->book();
        $member = Member::create(['name' => 'Dini', 'phone' => '08123456782']);
        $borrowing = Borrowing::create(['member_id' => $member->id, 'book_id' => $book->id, 'user_id' => $user->id, 'borrowed_at' => '2026-07-01', 'due_date' => '2026-07-08', 'returned_at' => '2026-07-08', 'status' => 'returned']);

        $this->actingAs($user)->from(route('borrowings.show', $borrowing))->post(route('borrowings.return', $borrowing), ['returned_at' => '2026-07-09'])
            ->assertRedirect(route('borrowings.show', $borrowing))->assertSessionHasErrors('returned_at');

        $this->assertSame(3, $book->fresh()->stock);
    }

    public function test_peminjaman_keempat_memblokir_akun_secara_otomatis(): void
    {
        $user = $this->user();
        $member = Member::create(['name' => 'Eka', 'phone' => '08123456783']);
        foreach (range(1, 3) as $number) {
            $book = $this->book();
            Borrowing::create(['member_id' => $member->id, 'book_id' => $book->id, 'user_id' => $user->id, 'borrowed_at' => '2026-07-01', 'due_date' => '2026-07-08', 'status' => 'borrowed']);
        }
        $fourthBook = $this->book();

        $this->actingAs($user)->from(route('borrowings.create'))->post(route('borrowings.store'), [
            'member_id' => $member->id, 'book_id' => $fourthBook->id,
            'borrowed_at' => '2026-07-01', 'due_date' => '2026-07-08',
        ])->assertRedirect(route('borrowings.create'))->assertSessionHasErrors('member_id');

        $this->assertDatabaseHas('members', ['id' => $member->id, 'account_status' => 'blocked', 'block_type' => 'automatic']);
        $this->assertDatabaseHas('warnings', ['member_id' => $member->id, 'type' => 'loan_limit']);
        $this->assertSame(3, Borrowing::where('member_id', $member->id)->count());
    }
}
