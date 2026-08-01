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
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupController extends Controller
{
    public function download(): StreamedResponse
    {
        $filename = 'ruang-baca-snapshot-'.now()->format('Ymd-His').'.json';

        return response()->streamDownload(function () {
            $snapshot = [
                'format' => 'ruang-baca-data-snapshot',
                'version' => 1,
                'created_at' => now()->toIso8601String(),
                'note' => 'Snapshot data operasional. Password pengguna tidak disertakan demi keamanan.',
                'data' => [
                    'users' => User::query()->get(['id', 'name', 'email', 'role', 'google_id', 'avatar_url', 'email_verified_at', 'created_at', 'updated_at']),
                    'categories' => Category::all(),
                    'books' => Book::all(),
                    'book_copies' => BookCopy::all(),
                    'members' => Member::withTrashed()->get(),
                    'borrowings' => Borrowing::all(),
                    'reservations' => BookReservation::all(),
                    'fines' => Fine::all(),
                    'fine_payments' => FinePayment::all(),
                    'settings' => SystemSetting::all(),
                ],
            ];
            echo json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }, $filename, ['Content-Type' => 'application/json; charset=UTF-8']);
    }
}
