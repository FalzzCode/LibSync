<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Borrowing;
use App\Models\Category;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookCoverRenderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_cover_ditampilkan_di_katalog_siswa_dan_daftar_pinjaman(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $member = Member::create([
            'user_id' => $student->id,
            'name' => 'Siswa Cover',
            'phone' => '08123456780',
        ]);
        $staff = User::factory()->create(['role' => 'staff']);
        $book = Book::create([
            'title' => 'Buku Dengan Cover',
            'author' => 'Penulis',
            'category_id' => Category::create(['name' => 'Koleksi Cover'])->id,
            'stock' => 1,
            'cover_image' => 'books/cover-test.jpg',
        ]);
        Borrowing::create([
            'member_id' => $member->id,
            'book_id' => $book->id,
            'user_id' => $staff->id,
            'borrowed_at' => today(),
            'due_date' => today()->addDays(7),
            'status' => 'borrowed',
        ]);

        $this->actingAs($student)
            ->get(route('student.catalog'))
            ->assertOk()
            ->assertSee('book-cover--student-catalog')
            ->assertSee('storage/books/cover-test.jpg');

        $this->actingAs($student)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('book-cover--loan')
            ->assertSee('storage/books/cover-test.jpg');
    }

    public function test_cover_ditampilkan_di_detail_koleksi_dan_detail_transaksi_petugas(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $member = Member::create(['name' => 'Peminjam Cover', 'phone' => '08123456781']);
        $book = Book::create([
            'title' => 'Detail Cover',
            'author' => 'Penulis',
            'category_id' => Category::create(['name' => 'Detail'])->id,
            'stock' => 0,
            'cover_image' => 'books/detail-cover.jpg',
        ]);
        $borrowing = Borrowing::create([
            'member_id' => $member->id,
            'book_id' => $book->id,
            'user_id' => $staff->id,
            'borrowed_at' => today(),
            'due_date' => today()->addDays(7),
            'status' => 'borrowed',
        ]);

        $this->actingAs($staff)
            ->get(route('books.show', $book))
            ->assertOk()
            ->assertSee('book-cover--detail')
            ->assertSee('storage/books/detail-cover.jpg');

        $this->actingAs($staff)
            ->get(route('borrowings.show', $borrowing))
            ->assertOk()
            ->assertSee('borrowing-detail__cover')
            ->assertSee('storage/books/detail-cover.jpg');
    }

    public function test_cover_bawaan_buku_indonesia_ditampilkan_tanpa_upload_manual(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $book = Book::create([
            'title' => 'Laut Bercerita',
            'author' => 'Leila S. Chudori',
            'book_code' => 'IND-001',
            'category_id' => Category::create(['name' => 'Fiksi Indonesia'])->id,
            'stock' => 1,
        ]);

        $this->actingAs($staff)
            ->get(route('books.show', $book))
            ->assertOk()
            ->assertSee('https://cdn.gramedia.com/uploads/product-metas/c7zl00re93.jpg');
    }
}
