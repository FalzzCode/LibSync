<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\BookCopy;
use App\Models\BookReservation;
use App\Models\Borrowing;
use App\Models\Category;
use App\Models\Fine;
use App\Models\FinePayment;
use App\Models\Member;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\ActivityLogger;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupController extends Controller
{
    public function download(): StreamedResponse
    {
        $filename = 'ruang-baca-snapshot-'.now()->format('Ymd-His').'.json';
        ActivityLogger::write('download', 'backup', null, null, ['filename' => $filename]);

        return response()->streamDownload(function () {
            $options = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR;
            echo '{';
            echo json_encode('format', $options).':'.json_encode('ruang-baca-data-snapshot', $options).',';
            echo json_encode('version', $options).':1,';
            echo json_encode('created_at', $options).':'.json_encode(now()->toIso8601String(), $options).',';
            echo json_encode('note', $options).':'.json_encode('Snapshot data operasional. Password dan hash kode aktivasi tidak disertakan demi keamanan.', $options).',';
            echo json_encode('data', $options).':{';

            $firstCollection = true;
            $this->writeCollection('users', User::query()->select(['id', 'name', 'email', 'role', 'google_id', 'avatar_url', 'email_verified_at', 'created_at', 'updated_at'])->cursor(), $firstCollection, $options);
            $this->writeCollection('categories', Category::query()->cursor(), $firstCollection, $options);
            $this->writeCollection('books', Book::query()->cursor(), $firstCollection, $options);
            $this->writeCollection('book_copies', BookCopy::query()->cursor(), $firstCollection, $options);
            $this->writeCollection('members', Member::withTrashed()->select([
                'id', 'name', 'class', 'address', 'phone', 'nis', 'nisn', 'major',
                'gender', 'email', 'entry_year', 'account_status', 'block_type',
                'block_reason', 'blocked_at', 'user_id', 'activation_expires_at',
                'activated_at', 'created_at', 'updated_at', 'deleted_at',
            ])->cursor(), $firstCollection, $options);
            $this->writeCollection('borrowings', Borrowing::query()->cursor(), $firstCollection, $options);
            $this->writeCollection('reservations', BookReservation::query()->cursor(), $firstCollection, $options);
            $this->writeCollection('fines', Fine::query()->cursor(), $firstCollection, $options);
            $this->writeCollection('fine_payments', FinePayment::query()->cursor(), $firstCollection, $options);
            $this->writeCollection('settings', SystemSetting::query()->cursor(), $firstCollection, $options);

            echo '}}';
        }, $filename, [
            'Content-Type' => 'application/json; charset=UTF-8',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    /**
     * Write one model collection without loading the entire table into memory.
     * The stream stays valid JSON while each row is read through a cursor.
     *
     * @param  iterable<object>  $rows
     */
    private function writeCollection(string $key, iterable $rows, bool &$firstCollection, int $options): void
    {
        if (! $firstCollection) {
            echo ',';
        }
        $firstCollection = false;
        echo json_encode($key, $options).':[';

        $firstRow = true;
        foreach ($rows as $row) {
            if (! $firstRow) {
                echo ',';
            }
            $firstRow = false;
            echo json_encode($row, $options);
        }

        echo ']';
    }
}
