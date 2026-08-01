<?php

namespace App\Http\Controllers;

use App\Models\Borrowing;
use App\Models\FinePayment;
use App\Models\Member;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $from = $request->date('from')?->startOfDay() ?? now()->startOfMonth();
        $until = $request->date('until')?->endOfDay() ?? now()->endOfDay();
        if ($from->greaterThan($until)) {
            [$from, $until] = [$until->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        $borrowings = Borrowing::query()->whereBetween('borrowed_at', [$from, $until]);
        $summary = [
            'borrowed' => (clone $borrowings)->count(),
            'returned' => (clone $borrowings)->where('status', 'returned')->count(),
            'overdue' => Borrowing::overdue()->count(),
            'fines_paid' => FinePayment::query()->whereBetween('paid_at', [$from, $until])->sum('amount'),
            'members' => Member::query()->whereBetween('created_at', [$from, $until])->count(),
        ];

        return view('reports.index', compact('from', 'until', 'summary'));
    }

    public function borrowingsCsv(Request $request): StreamedResponse
    {
        $filename = 'laporan-peminjaman-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($request) {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            $this->writeCsvRow($output, ['ID', 'Anggota', 'Buku', 'Status', 'Tanggal pinjam', 'Jatuh tempo', 'Tanggal kembali', 'Denda']);
            Borrowing::with(['member:id,name', 'book:id,title'])
                ->when($request->filled('from'), fn ($query) => $query->whereDate('borrowed_at', '>=', $request->from))
                ->when($request->filled('until'), fn ($query) => $query->whereDate('borrowed_at', '<=', $request->until))
                ->latest('id')
                ->chunk(200, function ($borrowings) use ($output) {
                    foreach ($borrowings as $borrowing) {
                        $this->writeCsvRow($output, [$borrowing->id, $borrowing->member->name, $borrowing->book->title, $borrowing->status, $borrowing->borrowed_at?->format('Y-m-d'), $borrowing->due_date?->format('Y-m-d'), $borrowing->returned_at?->format('Y-m-d'), $borrowing->fine]);
                    }
                });
            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function finePaymentsCsv(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            $this->writeCsvRow($output, ['ID Pembayaran', 'Anggota', 'Nominal', 'Metode', 'Diterima oleh', 'Tanggal bayar', 'Catatan']);
            FinePayment::with(['fine.member:id,name', 'receiver:id,name'])->latest('paid_at')->chunk(200, function ($payments) use ($output) {
                foreach ($payments as $payment) {
                    $this->writeCsvRow($output, [$payment->id, $payment->fine->member->name, $payment->amount, $payment->method, $payment->receiver?->name, $payment->paid_at?->format('Y-m-d H:i'), $payment->note]);
                }
            });
            fclose($output);
        }, 'laporan-pembayaran-denda-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
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
