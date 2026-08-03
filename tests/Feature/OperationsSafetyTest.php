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

        $this->assertDatabaseHas('denda', ['id' => $fine->id, 'paid_amount' => 2000, 'status' => 'partial']);
        $this->assertDatabaseHas('pembayaran_denda', ['fine_id' => $fine->id, 'amount' => 2000, 'received_by' => $staff->id]);

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

        $this->assertDatabaseHas('kategori', ['id' => $book->category_id]);
        $this->assertDatabaseHas('buku', ['id' => $book->id]);
        $this->assertDatabaseHas('reservasi_buku', ['id' => $reservation->id]);
    }

    public function test_anggota_dengan_antrean_buku_tidak_dapat_dihapus(): void
    {
        $staff = $this->staff();
        $member = Member::create(['name' => 'Anggota Antrean', 'phone' => '08123456763']);
        $book = $this->book();
        $reservation = BookReservation::create([
            'book_id' => $book->id,
            'member_id' => $member->id,
            'status' => 'waiting',
            'queue_position' => 1,
        ]);

        $this->actingAs($staff)->from(route('members.index'))
            ->delete(route('members.destroy', $member))
            ->assertRedirect(route('members.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('anggota', ['id' => $member->id]);
        $this->assertDatabaseHas('reservasi_buku', ['id' => $reservation->id]);
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

        $this->assertDatabaseCount('salinan_buku', 1);
    }

    public function test_kode_koleksi_buku_tidak_boleh_berulang(): void
    {
        $staff = $this->staff();
        $firstBook = $this->book();
        $firstBook->update(['book_code' => 'BK-001']);
        $secondBook = Book::create([
            'title' => 'Buku Kedua',
            'author' => 'Penulis Kedua',
            'stock' => 1,
            'category_id' => $firstBook->category_id,
        ]);

        $this->actingAs($staff)->from(route('books.edit', $secondBook))->put(route('books.update', $secondBook), [
            'title' => $secondBook->title,
            'author' => $secondBook->author,
            'book_code' => 'BK-001',
            'category_id' => $secondBook->category_id,
            'stock' => 1,
        ])->assertRedirect(route('books.edit', $secondBook))->assertSessionHasErrors('book_code');
    }
}
