<?php

namespace App\Console\Commands;

use App\Models\Borrowing;
use App\Models\Warning;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckOverdueBorrowings extends Command
{
    protected $signature = 'library:check-overdues';

    protected $description = 'Membuat peringatan untuk transaksi yang telah melewati tanggal jatuh tempo.';

    public function handle(): int
    {
        $count = 0;
        Borrowing::query()
            ->overdue()
            ->select('id')
            ->chunkById(200, function ($borrowings) use (&$count): void {
                foreach ($borrowings as $borrowing) {
                    $borrowingId = (int) $borrowing->id;
                    DB::transaction(function () use ($borrowingId, &$count): void {
                        // Two scheduler workers can start with the same snapshot. By
                        // locking the borrowing before checking its warning, the
                        // second worker observes the warning created by the first.
                        $borrowing = Borrowing::query()
                            ->whereKey($borrowingId)
                            ->overdue()
                            ->with(['member', 'book'])
                            ->lockForUpdate()
                            ->first();
                        if (! $borrowing) {
                            return;
                        }

                        $warning = Warning::query()
                            ->where('borrowing_id', $borrowing->id)
                            ->where('type', 'overdue')
                            ->whereNull('resolved_at')
                            ->lockForUpdate()
                            ->first();
                        if ($warning) {
                            return;
                        }

                        $days = $borrowing->due_date->diffInDays(today());
                        Warning::create([
                            'member_id' => $borrowing->member_id, 'borrowing_id' => $borrowing->id,
                            'type' => 'overdue', 'level' => $days > 7 ? 'critical' : 'warning',
                            'title' => 'Peminjaman terlambat',
                            'message' => "{$borrowing->member->name} terlambat mengembalikan {$borrowing->book->title} selama {$days} hari.",
                        ]);
                        $count++;
                    });
                }
            });

        $this->info("Pengecekan selesai. {$count} peringatan baru dibuat.");

        return self::SUCCESS;
    }
}
