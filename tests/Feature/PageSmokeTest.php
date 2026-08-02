<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Borrowing;
use App\Models\Category;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageSmokeTest extends TestCase
{
    use RefreshDatabase;

    private function fixtures(): array
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $staff = User::factory()->create(['role' => 'staff']);
        $student = User::factory()->create(['role' => 'student']);
        $category = Category::create(['name' => 'Koleksi Uji']);
        $book = Book::create(['title' => 'Buku Uji', 'author' => 'Penulis Uji', 'category_id' => $category->id, 'stock' => 1]);
        $member = Member::create(['user_id' => $student->id, 'name' => 'Siswa Uji', 'phone' => '08123456767']);
        $borrowing = Borrowing::create([
            'member_id' => $member->id,
            'book_id' => $book->id,
            'user_id' => $staff->id,
            'borrowed_at' => today(),
            'due_date' => today()->addDays(7),
            'status' => 'borrowed',
        ]);

        return compact('admin', 'staff', 'student', 'category', 'book', 'member', 'borrowing');
    }

    public function test_admin_dapat_merender_seluruh_halaman_administrasi_utama(): void
    {
        ['admin' => $admin, 'category' => $category, 'book' => $book, 'member' => $member, 'borrowing' => $borrowing] = $this->fixtures();

        foreach ([
            route('dashboard'), route('profile.edit'), route('categories.index'), route('categories.create'), route('categories.edit', $category),
            route('books.index'), route('books.create'), route('books.show', $book), route('books.edit', $book), route('book-copies.index'),
            route('members.index'), route('members.create'), route('members.edit', $member), route('borrowings.index'), route('borrowings.create'),
            route('borrowings.show', $borrowing), route('warnings.index'), route('fines.index'), route('imports.create'), route('reports.index'),
            route('users.index'), route('users.create'), route('settings.edit'),
        ] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    public function test_staff_dan_siswa_hanya_melihat_halaman_sesuai_role(): void
    {
        ['staff' => $staff, 'student' => $student, 'book' => $book] = $this->fixtures();

        $this->actingAs($staff)->get(route('dashboard'))->assertOk();
        $this->actingAs($staff)->get(route('books.index'))->assertOk();
        $this->actingAs($staff)->get(route('reports.index'))->assertOk();
        $this->actingAs($staff)->get(route('users.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('settings.edit'))->assertForbidden();

        $this->actingAs($student)->get(route('student.dashboard'))->assertOk();
        $this->actingAs($student)->get(route('student.catalog'))->assertOk();
        $this->actingAs($student)->get(route('books.show', $book))->assertForbidden();
    }

    public function test_dashboard_shows_an_empty_chart_until_the_first_transaction_exists(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('transaction-stats--enhanced')
            ->assertSee('data-solar-icon="solar:book-2-bold"', false)
            ->assertSee('data-solar-icon="solar:wallet-money-bold"', false)
            ->assertSee('Pinjaman aktif')
            ->assertSee('Belum ada aktivitas')
            ->assertSee('Grafik akan mulai terisi setelah peminjaman pertama dicatat.');
    }

    public function test_dashboard_chart_starts_from_the_first_transaction_day(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $staff = User::factory()->create(['role' => 'staff']);
        $student = User::factory()->create(['role' => 'student']);
        $category = Category::create(['name' => 'Koleksi Uji']);
        $book = Book::create(['title' => 'Buku Uji', 'author' => 'Penulis Uji', 'category_id' => $category->id, 'stock' => 1]);
        $member = Member::create(['user_id' => $student->id, 'name' => 'Siswa Uji', 'phone' => '08123456767']);
        Borrowing::create([
            'member_id' => $member->id,
            'book_id' => $book->id,
            'user_id' => $staff->id,
            'borrowed_at' => today(),
            'due_date' => today()->addDays(7),
            'status' => 'borrowed',
        ]);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Hari ini')
            ->assertViewHas('weeklyStats', fn ($stats) => $stats->count() === 1 && $stats->first()['borrowed'] === 1);
    }
}
