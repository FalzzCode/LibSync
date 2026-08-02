<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_and_category_filters_are_combined_instead_of_bypassing_category(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $fiction = Category::create(['name' => 'Fiksi']);
        $science = Category::create(['name' => 'Sains']);
        Book::create(['title' => 'Target Fiksi', 'author' => 'Penulis', 'category_id' => $fiction->id, 'stock' => 1]);
        Book::create(['title' => 'Buku Sains', 'author' => 'Target Penulis', 'category_id' => $science->id, 'stock' => 1]);

        $response = $this->actingAs($staff)->get(route('books.index', [
            'search' => 'Target',
            'category' => $science->id,
        ]));

        $response->assertOk()
            ->assertSee('Buku Sains')
            ->assertDontSee('Target Fiksi');
    }
}
