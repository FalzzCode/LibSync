<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Borrowing;
use App\Models\Category;
use App\Models\Fine;
use App\Models\FinePayment;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_ekspor_csv_menetralisir_formula_spreadsheet(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $member = Member::create(['name' => '=FormulaBerbahaya()', 'phone' => '08123456769']);
        $book = Book::create([
            'title' => '+JudulFormula',
            'author' => 'Penulis',
            'stock' => 1,
            'category_id' => Category::create(['name' => 'Uji'])->id,
        ]);
        Borrowing::create([
            'member_id' => $member->id,
            'book_id' => $book->id,
            'user_id' => $staff->id,
            'borrowed_at' => today(),
            'due_date' => today()->addDays(7),
            'status' => 'borrowed',
        ]);

        $response = $this->actingAs($staff)->get(route('reports.borrowings.csv'));

        $response->assertOk();
        $content = $response->streamedContent();
        $this->assertStringContainsString("'=FormulaBerbahaya()", $content);
        $this->assertStringContainsString("'+JudulFormula", $content);
        $this->assertStringContainsString('Sedang dipinjam', $content);
    }

    public function test_ekspor_pembayaran_denda_mengikuti_periode_yang_diminta(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $member = Member::create(['name' => 'Pembaca Uji', 'phone' => '08123456770']);
        $book = Book::create([
            'title' => 'Buku Denda',
            'author' => 'Penulis',
            'stock' => 1,
            'category_id' => Category::create(['name' => 'Denda'])->id,
        ]);
        $borrowing = Borrowing::create([
            'member_id' => $member->id,
            'book_id' => $book->id,
            'user_id' => $staff->id,
            'borrowed_at' => today(),
            'due_date' => today()->addDays(7),
            'status' => 'returned',
            'returned_at' => today(),
        ]);
        $fine = Fine::create(['member_id' => $member->id, 'borrowing_id' => $borrowing->id, 'type' => 'late', 'amount' => 1000, 'paid_amount' => 1000, 'status' => 'paid']);
        FinePayment::create(['fine_id' => $fine->id, 'amount' => 1000, 'method' => 'cash', 'received_by' => $staff->id, 'paid_at' => now()->subDays(20)]);
        FinePayment::create(['fine_id' => $fine->id, 'amount' => 500, 'method' => 'transfer', 'received_by' => $staff->id, 'paid_at' => now()->subDay()]);

        $response = $this->actingAs($staff)->get(route('reports.fine-payments.csv', [
            'from' => now()->subDays(2)->toDateString(),
            'until' => now()->toDateString(),
        ]));

        $response->assertOk();
        $content = $response->streamedContent();
        $this->assertStringContainsString('500', $content);
        $this->assertStringNotContainsString('1000,"Tunai"', $content);
    }
}
