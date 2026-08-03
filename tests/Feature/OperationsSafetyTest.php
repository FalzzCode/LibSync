<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookCopy;
use App\Models\BookReservation;
use App\Models\Category;
use App\Models\Fine;
use App\Models\Member;
use App\Models\User;
use App\Models\Warning;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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

    public function test_buku_dengan_salinan_fisik_tidak_dapat_dihapus(): void
    {
        $staff = $this->staff();
        $book = $this->book();
        $copy = BookCopy::create([
            'book_id' => $book->id,
            'inventory_code' => 'INV-HOLD-001',
            'condition' => 'good',
            'status' => 'available',
        ]);

        $this->actingAs($staff)->from(route('books.index'))
            ->delete(route('books.destroy', $book))
            ->assertRedirect(route('books.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('buku', ['id' => $book->id]);
        $this->assertDatabaseHas('salinan_buku', ['id' => $copy->id]);
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

    public function test_anggota_dengan_catatan_denda_tidak_dapat_dihapus(): void
    {
        $staff = $this->staff();
        $member = Member::create(['name' => 'Anggota Denda Hapus', 'phone' => '08123456762']);
        $fine = Fine::create(['member_id' => $member->id, 'amount' => 5000, 'type' => 'late', 'status' => 'unpaid']);

        $this->actingAs($staff)->from(route('members.index'))
            ->delete(route('members.destroy', $member))
            ->assertRedirect(route('members.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('anggota', ['id' => $member->id]);
        $this->assertDatabaseHas('denda', ['id' => $fine->id]);
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

    public function test_eksemplar_baru_tidak_dapat_ditambahkan_ke_buku_arsip(): void
    {
        $staff = $this->staff();
        $book = $this->book();
        $book->update(['archived_at' => now()]);

        $this->actingAs($staff)->from(route('book-copies.index'))
            ->post(route('book-copies.store'), [
                'book_id' => $book->id,
                'inventory_code' => 'INV-ARSIP-001',
                'condition' => 'good',
            ])
            ->assertRedirect(route('book-copies.index'))
            ->assertSessionHasErrors('book_id');

        $this->assertDatabaseCount('salinan_buku', 0);
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

    public function test_database_menolak_kode_koleksi_buku_duplikat(): void
    {
        $firstBook = $this->book();
        $firstBook->update(['book_code' => 'BK-DB-001']);

        $this->expectException(QueryException::class);
        Book::create([
            'title' => 'Buku Duplikat Database',
            'author' => 'Penulis',
            'stock' => 1,
            'book_code' => 'BK-DB-001',
            'category_id' => $firstBook->category_id,
        ]);
    }

    public function test_database_menolak_email_anggota_duplikat(): void
    {
        Member::create([
            'name' => 'Anggota Pertama',
            'email' => 'anggota@example.test',
            'phone' => '08123456760',
        ]);

        $this->expectException(QueryException::class);
        Member::create([
            'name' => 'Anggota Kedua',
            'email' => 'anggota@example.test',
            'phone' => '08123456761',
        ]);
    }

    public function test_database_menolak_nama_kategori_duplikat(): void
    {
        Category::create(['name' => 'Kategori Tunggal']);

        $this->expectException(QueryException::class);
        Category::create(['name' => 'Kategori Tunggal']);
    }

    public function test_resolve_peringatan_idempoten_saat_tombol_diklik_dua_kali(): void
    {
        $staff = $this->staff();
        $warning = Warning::create([
            'type' => 'manual',
            'level' => 'warning',
            'title' => 'Peringatan uji',
            'message' => 'Pesan uji.',
        ]);

        $this->actingAs($staff)->post(route('warnings.resolve', $warning), [
            'resolution_note' => 'Sudah ditangani.',
        ])->assertRedirect();

        $this->actingAs($staff)->post(route('warnings.resolve', $warning), [
            'resolution_note' => 'Catatan kedua tidak boleh menimpa.',
        ])->assertRedirect();

        $this->assertDatabaseHas('peringatan', [
            'id' => $warning->id,
            'resolution_note' => 'Sudah ditangani.',
        ]);
        $this->assertDatabaseCount('log_aktivitas', 1);
    }

    public function test_cover_buku_menolak_svg_aktif(): void
    {
        $staff = $this->staff();
        $category = Category::create(['name' => 'Kategori Cover Aman']);

        $this->actingAs($staff)->from(route('books.create'))
            ->post(route('books.store'), [
                'title' => 'Buku Tanpa SVG',
                'author' => 'Penulis',
                'category_id' => $category->id,
                'stock' => 1,
                'cover_image' => UploadedFile::fake()->createWithContent('cover.svg', '<svg><script>alert(1)</script></svg>'),
            ])
            ->assertRedirect(route('books.create'))
            ->assertSessionHasErrors('cover_image');

        $this->assertDatabaseCount('buku', 0);
    }
}
