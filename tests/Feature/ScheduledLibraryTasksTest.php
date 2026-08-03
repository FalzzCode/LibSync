<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookReservation;
use App\Models\Borrowing;
use App\Models\Category;
use App\Models\Member;
use App\Models\User;
use App\Models\Warning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduledLibraryTasksTest extends TestCase
{
    use RefreshDatabase;

    private function book(): Book
    {
        return Book::create([
            'title' => 'Buku Scheduler',
            'author' => 'Penulis',
            'stock' => 0,
            'category_id' => Category::firstOrCreate(['name' => 'Umum'])->id,
        ]);
    }

    public function test_pengecekan_terlambat_membuat_satu_peringatan_dan_bersifat_idempoten(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $member = Member::create(['name' => 'Anggota Terlambat', 'phone' => '08123456772']);
        $book = $this->book();
        Borrowing::create([
            'member_id' => $member->id,
            'book_id' => $book->id,
            'user_id' => $staff->id,
            'borrowed_at' => today()->subDays(10),
            'due_date' => today()->subDay(),
            'status' => 'borrowed',
        ]);

        $this->artisan('library:check-overdues')->assertExitCode(0);
        $this->artisan('library:check-overdues')->assertExitCode(0);

        $this->assertDatabaseCount('peringatan', 1);
        $this->assertDatabaseHas('peringatan', ['member_id' => $member->id, 'type' => 'overdue']);
    }

    public function test_reservasi_kedaluwarsa_dialihkan_ke_antrean_berikutnya(): void
    {
        $book = $this->book();
        $expiredMember = Member::create(['name' => 'Antrean Kedaluwarsa', 'phone' => '08123456771']);
        $nextUser = User::factory()->create(['role' => 'student']);
        $nextMember = Member::create(['user_id' => $nextUser->id, 'name' => 'Antrean Berikutnya', 'phone' => '08123456770']);
        $expired = BookReservation::create([
            'book_id' => $book->id,
            'member_id' => $expiredMember->id,
            'status' => 'ready',
            'queue_position' => 1,
            'expires_at' => now()->subMinute(),
        ]);
        $next = BookReservation::create([
            'book_id' => $book->id,
            'member_id' => $nextMember->id,
            'status' => 'waiting',
            'queue_position' => 2,
        ]);

        $this->artisan('library:process-reservations')->assertExitCode(0);

        $this->assertDatabaseHas('reservasi_buku', ['id' => $expired->id, 'status' => 'expired']);
        $this->assertDatabaseHas('reservasi_buku', ['id' => $next->id, 'status' => 'ready']);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $nextUser->id]);
    }

    public function test_pengembalian_menutup_peringatan_terlambat_yang_sudah_terbuka(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $member = Member::create(['name' => 'Anggota Selesai', 'phone' => '08123456769']);
        $book = $this->book();
        $borrowing = Borrowing::create([
            'member_id' => $member->id,
            'book_id' => $book->id,
            'user_id' => $staff->id,
            'borrowed_at' => today()->subDays(10),
            'due_date' => today()->subDay(),
            'status' => 'borrowed',
        ]);
        $warning = Warning::create([
            'member_id' => $member->id,
            'borrowing_id' => $borrowing->id,
            'type' => 'overdue',
            'level' => 'warning',
            'title' => 'Peminjaman terlambat',
            'message' => 'Peringatan uji.',
        ]);

        $this->actingAs($staff)->post(route('borrowings.return', $borrowing), [
            'returned_at' => today()->toDateString(),
        ])->assertRedirect();

        $updatedWarning = $warning->fresh();
        $this->assertNotNull($updatedWarning?->resolved_at);
        $this->assertSame('Peminjaman sudah dikembalikan.', $updatedWarning?->resolution_note);
    }
}
