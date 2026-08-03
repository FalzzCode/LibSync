<?php

namespace App\Http\Controllers;

use App\Models\Borrowing;
use App\Models\FinePayment;
use App\Models\Member;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    private const BORROWING_STATUS_LABELS = [
        'requested' => 'Menunggu persetujuan',
        'return_requested' => 'Pengembalian diajukan',
        'borrowed' => 'Sedang dipinjam',
        'returned' => 'Dikembalikan',
        'rejected' => 'Ditolak',
    ];

    private const PAYMENT_METHOD_LABELS = [
        'cash' => 'Tunai',
        'transfer' => 'Transfer',
        'qris' => 'QRIS',
        'waived' => 'Dibebaskan',
    ];

    public function index(Request $request): View
    {
        [$from, $until] = $this->dateRange($request);

        $borrowings = Borrowing::query()->whereBetween('borrowed_at', [$from, $until]);
        $summary = [
            'borrowed' => (clone $borrowings)->count(),
            'returned' => (clone $borrowings)->where('status', 'returned')->count(),
            'overdue' => (clone $borrowings)->overdue()->count(),
            'fines_paid' => FinePayment::query()->whereBetween('paid_at', [$from, $until])->sum('amount'),
            'members' => Member::query()->whereBetween('created_at', [$from, $until])->count(),
        ];

        return view('reports.index', compact('from', 'until', 'summary'));
    }

    public function borrowingsCsv(Request $request): StreamedResponse
    {
        [$from, $until] = $this->dateRange($request, includeDefaults: false);
        $filename = 'laporan-peminjaman-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($from, $until) {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            $this->writeCsvRow($output, ['ID', 'Anggota', 'Buku', 'Status', 'Tanggal pinjam', 'Jatuh tempo', 'Tanggal kembali', 'Denda']);
            Borrowing::with(['member:id,name', 'book:id,title'])
                ->when($from, fn ($query) => $query->where('borrowed_at', '>=', $from))
                ->when($until, fn ($query) => $query->where('borrowed_at', '<=', $until))
                ->latest('id')
                ->chunk(200, function ($borrowings) use ($output) {
                    foreach ($borrowings as $borrowing) {
                        $this->writeCsvRow($output, [$borrowing->id, $borrowing->member->name, $borrowing->book->title, self::BORROWING_STATUS_LABELS[$borrowing->status] ?? $borrowing->status, $borrowing->borrowed_at?->format('Y-m-d'), $borrowing->due_date?->format('Y-m-d'), $borrowing->returned_at?->format('Y-m-d'), $borrowing->fine]);
                    }
                });
            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Normalize report dates in one place so the screen and CSV export always
     * describe the same period. Reversed dates are corrected instead of
     * returning an empty report that looks like a data loss.
     *
     * @return array{0: Carbon|null, 1: Carbon|null}
     */
    private function dateRange(Request $request, bool $includeDefaults = true): array
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'until' => ['nullable', 'date'],
        ]);

        $from = $request->date('from');
        $until = $request->date('until');

        if (! $from && ! $until && ! $includeDefaults) {
            return [null, null];
        }

        $from = ($from ?? now()->startOfMonth())->startOfDay();
        $until = ($until ?? now())->endOfDay();

        if ($from->greaterThan($until)) {
            [$from, $until] = [$until->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        return [$from, $until];
    }

    public function finePaymentsCsv(Request $request): StreamedResponse
    {
        [$from, $until] = $this->dateRange($request, includeDefaults: false);
        $filename = 'laporan-pembayaran-denda-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($from, $until) {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            $this->writeCsvRow($output, ['ID Pembayaran', 'Anggota', 'Nominal', 'Metode', 'Diterima oleh', 'Tanggal bayar', 'Catatan']);
            FinePayment::with(['fine.member:id,name', 'receiver:id,name'])
                ->when($from, fn ($query) => $query->where('paid_at', '>=', $from))
                ->when($until, fn ($query) => $query->where('paid_at', '<=', $until))
                ->latest('paid_at')
                ->chunk(200, function ($payments) use ($output) {
                    foreach ($payments as $payment) {
                        $this->writeCsvRow($output, [$payment->id, $payment->fine->member->name, $payment->amount, self::PAYMENT_METHOD_LABELS[$payment->method] ?? $payment->method, $payment->receiver?->name, $payment->paid_at?->format('Y-m-d H:i'), $payment->note]);
                    }
                });
            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @param resource $output */
    private function writeCsvRow($output, array $row): void
    {
        fputcsv($output, array_map(function ($value) {
            if (is_string($value) && preg_match('/^[=+\-@]/', $value)) {
                return "'{$value}";
            }

            return $value;
        }, $row));
    }
}
