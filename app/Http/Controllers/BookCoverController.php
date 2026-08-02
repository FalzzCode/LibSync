<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BookCoverController extends Controller
{
    public function show(Book $book): StreamedResponse
    {
        $path = str_replace('\\', '/', ltrim((string) $book->cover_image, '/'));

        abort_unless($path !== '' && str_starts_with($path, 'books/') && ! str_contains($path, '..'), 404);
        abort_unless(Storage::disk('public')->exists($path), 404);

        return Storage::disk('public')->response($path, headers: [
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
