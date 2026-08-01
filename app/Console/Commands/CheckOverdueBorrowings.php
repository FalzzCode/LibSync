<?php

namespace App\Console\Commands;

use App\Models\Borrowing;
use App\Models\Warning;
use Illuminate\Console\Command;

class CheckOverdueBorrowings extends Command
{
    protected $signature = 'library:check-overdues';

    protected $description = 'Membuat peringatan untuk transaksi yang telah melewati tanggal jatuh tempo.';

    public function handle(): int
    {
        $count = 0;
        Borrowing::query()->with(['member', 'book'])->overdue()->each(function (Borrowing $borrowing) use (&$count) {
            $warning = Warning::query()->where('borrowing_id', $borrowing->id)->where('type', 'overdue')->whereNull('resolved_at')->first();
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

        $this->info("Pengecekan selesai. {$count} peringatan baru dibuat.");

        return self::SUCCESS;
    }
}
