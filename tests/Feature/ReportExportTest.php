<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Borrowing;
use App\Models\Category;
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
    }
}
