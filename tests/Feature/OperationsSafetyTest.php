<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookReservation;
use App\Models\Category;
use App\Models\Fine;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationsSafetyTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        return User::factory()->create(['role' => 'staff']);
    }

    private function book(): Book
    {
        return Book::create([
            'title' => 'Buku Operasional',
            'author' => 'Penulis',
            'stock' => 2,
            'category_id' => Category::firstOrCreate(['name' => 'Operasional'])->id,
        ]);
    }

    public function test_pembayaran_denda_memperbarui_sisa_dan_menolak_nominal_berlebih(): void
    {
        $staff = $this->staff();
        $member = Member::create(['name' => 'Anggota Denda', 'phone' => '08123456766']);
        $fine = Fine::create(['member_id' => $member->id, 'amount' => 5000, 'type' => 'late', 'status' => 'unpaid']);

        $this->actingAs($staff)->post(route('fines.pay', $fine), [
            'amount' => 2000, 'method' => 'cash',
        ])->assertRedirect();

        $this->assertDatabaseHas('fines', ['id' => $fine->id, 'paid_amount' => 2000, 'status' => 'partial']);
        $this->assertDatabaseHas('fine_payments', ['fine_id' => $fine->id, 'amount' => 2000, 'received_by' => $staff->id]);

        $this->actingAs($staff)->from(route('fines.index'))->post(route('fines.pay', $fine), [
            'amount' => 4000, 'method' => 'cash',
        ])->assertRedirect(route('fines.index'))->assertSessionHasErrors('amount');

        $this->assertSame(2000, $fine->fresh()->paid_amount);
    }

    public function test_kategori_dan_buku_dengan_relasi_aktif_tidak_dapat_dihapus(): void
    {
        $staff = $this->staff();
        $book = $this->book();
        $reservationMember = Member::create(['name' => 'Antrean Hapus', 'phone' => '08123456765']);
        $reservation = BookReservation::create([
            'book_id' => $book->id,
            'member_id' => $reservationMember->id,
            'status' => 'waiting',
            'queue_position' => 1,
        ]);

        $this->actingAs($staff)->from(route('categories.index'))->delete(route('categories.destroy', $book->category))
            ->assertRedirect(route('categories.index'))->assertSessionHas('error');
        $this->actingAs($staff)->from(route('books.index'))->delete(route('books.destroy', $book))
            ->assertRedirect(route('books.index'))->assertSessionHas('error');

        $this->assertDatabaseHas('categories', ['id' => $book->category_id]);
        $this->assertDatabaseHas('books', ['id' => $book->id]);
        $this->assertDatabaseHas('book_reservations', ['id' => $reservation->id]);
    }

    public function test_kode_eksemplar_harus_unik(): void
    {
        $staff = $this->staff();
        $book = $this->book();

        $this->actingAs($staff)->post(route('book-copies.store'), [
            'book_id' => $book->id,
            'inventory_code' => 'INV-001',
            'condition' => 'good',
        ])->assertRedirect();

        $this->actingAs($staff)->from(route('book-copies.index'))->post(route('book-copies.store'), [
            'book_id' => $book->id,
            'inventory_code' => 'INV-001',
            'condition' => 'good',
        ])->assertRedirect(route('book-copies.index'))->assertSessionHasErrors('inventory_code');

        $this->assertDatabaseCount('book_copies', 1);
    }
}
