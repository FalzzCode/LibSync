<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Borrowing;
use App\Models\Category;
use App\Models\Member;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    // Menampilkan halaman dashboard beserta statistik dinamis dari database
    public function index(): View|RedirectResponse
    {
        if (auth()->user()->role === 'student') {
            return redirect()->route('student.dashboard');
        }
        if (auth()->user()->role === 'developer') {
            return redirect()->route('developer.index');
        }
        $totalBooks = Book::count();
        $totalCategories = Category::count();
        $totalMembers = Member::count();
        // Student portal accounts are members, not staff users managed from
        // the admin screen. Keep this KPI aligned with the dashboard label.
        $totalUsers = User::whereIn('role', ['admin', 'staff'])->count();
        $activeBorrowings = Borrowing::open()->count();
        $returnedBorrowings = Borrowing::where('status', 'returned')->count();
        $overdueBorrowings = Borrowing::overdue()->count();
        $totalFines = Borrowing::where('status', 'returned')->sum('fine');
        $recentBorrowings = Borrowing::with(['member', 'book'])->latest()->take(5)->get();
        $firstBorrowingDate = Borrowing::min('borrowed_at');
        $periodEnd = today();
        $defaultPeriodStart = $periodEnd->copy()->subDays(6);
        $periodStart = $firstBorrowingDate
            ? Carbon::parse($firstBorrowingDate)->startOfDay()->max($defaultPeriodStart)
            : null;
        $weeklyStats = collect();

        if ($periodStart) {
            $recentForChart = Borrowing::where(function ($query) use ($periodStart) {
                $query->whereDate('borrowed_at', '>=', $periodStart)
                    ->orWhereDate('returned_at', '>=', $periodStart);
            })->get();
            $borrowedByDay = $recentForChart->filter(fn (Borrowing $borrowing) => $borrowing->borrowed_at->betweenIncluded($periodStart, $periodEnd))->countBy(fn (Borrowing $borrowing) => $borrowing->borrowed_at->toDateString());
            $returnedByDay = $recentForChart->filter(fn (Borrowing $borrowing) => $borrowing->returned_at?->betweenIncluded($periodStart, $periodEnd))->countBy(fn (Borrowing $borrowing) => $borrowing->returned_at->toDateString());
            $weeklyStats = collect(range(0, $periodStart->diffInDays($periodEnd)))->map(function (int $offset) use ($periodStart, $borrowedByDay, $returnedByDay) {
                $date = $periodStart->copy()->addDays($offset);

                return ['label' => $date->isToday() ? 'Hari ini' : $date->translatedFormat('D'), 'borrowed' => $borrowedByDay->get($date->toDateString(), 0), 'returned' => $returnedByDay->get($date->toDateString(), 0)];
            });
        }
        $circulationPeriodLabel = $weeklyStats->isEmpty() ? 'Belum ada aktivitas' : ($weeklyStats->count() === 1 ? 'Hari ini' : $weeklyStats->count().' hari terakhir');
        $circulationChartMaximum = max(1, $weeklyStats->flatMap(fn (array $day) => [$day['borrowed'], $day['returned']])->max());
        $popularBooks = Borrowing::select('book_id', DB::raw('count(*) as transaction_count'))->with('book:id,title')->groupBy('book_id')->orderByDesc('transaction_count')->take(3)->get();
        $activeMembers = Borrowing::select('member_id', DB::raw('count(*) as transaction_count'))->with('member:id,name')->groupBy('member_id')->orderByDesc('transaction_count')->take(3)->get();
        $actionItems = collect([
            ['label' => 'Permintaan pinjam', 'icon' => 'solar:clock-circle-linear', 'fallback' => '⌛', 'count' => Borrowing::where('status', 'requested')->count(), 'description' => 'Siswa menunggu persetujuan peminjaman.', 'url' => route('borrowings.index', ['status' => 'requested'])],
            ['label' => 'Permintaan kembali', 'icon' => 'solar:alt-arrow-left-linear', 'fallback' => '↩', 'count' => Borrowing::where('status', 'return_requested')->count(), 'description' => 'Buku sudah diajukan untuk dikembalikan.', 'url' => route('borrowings.index', ['status' => 'return_requested'])],
            ['label' => 'Perpanjangan', 'icon' => 'solar:refresh-circle-linear', 'fallback' => '↗', 'count' => Borrowing::whereNotNull('extension_requested_at')->whereIn('status', ['borrowed', 'return_requested'])->count(), 'description' => 'Permintaan perpanjangan perlu diperiksa.', 'url' => route('borrowings.index', ['status' => 'extension_requested'])],
            ['label' => 'Terlambat', 'icon' => 'solar:danger-triangle-linear', 'fallback' => '!', 'count' => $overdueBorrowings, 'description' => 'Segera hubungi anggota yang melewati jatuh tempo.', 'url' => route('borrowings.index', ['status' => 'overdue'])],
        ]);

        return view('dashboard', compact('totalBooks', 'totalCategories', 'totalMembers', 'totalUsers', 'activeBorrowings', 'returnedBorrowings', 'overdueBorrowings', 'totalFines', 'recentBorrowings', 'weeklyStats', 'circulationPeriodLabel', 'circulationChartMaximum', 'popularBooks', 'activeMembers', 'actionItems'));
    }
}
