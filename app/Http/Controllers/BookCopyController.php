<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\BookCopy;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BookCopyController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:120']]);
        $search = trim((string) ($filters['search'] ?? ''));
        $copies = BookCopy::with('book:id,title,book_code')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(fn ($q) => $q->where('inventory_code', 'like', "%{$search}%")->orWhere('barcode', 'like', "%{$search}%")->orWhereHas('book', fn ($book) => $book->where('title', 'like', "%{$search}%")));
            })->latest()->get();
        $books = Book::query()
            ->whereNull('archived_at')
            ->orderBy('title')
            ->get(['id', 'title', 'book_code']);

        return view('book-copies.index', compact('copies', 'books'));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'inventory_code' => trim((string) $request->input('inventory_code')),
            'barcode' => trim((string) $request->input('barcode')),
        ]);
        $data = $request->validate([
            'book_id' => ['required', 'exists:buku,id'],
            'inventory_code' => ['required', 'string', 'max:100', 'unique:salinan_buku,inventory_code'],
            'barcode' => ['nullable', 'string', 'max:100', 'unique:salinan_buku,barcode'],
            'condition' => ['required', 'in:good,minor_damage,damaged'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);
        $copy = DB::transaction(function () use ($data): BookCopy {
            $book = Book::query()->lockForUpdate()->findOrFail($data['book_id']);
            if ($book->archived_at) {
                throw ValidationException::withMessages(['book_id' => 'Buku yang diarsipkan tidak dapat menerima eksemplar baru.']);
            }

            return BookCopy::create($data);
        });
        ActivityLogger::write('create', 'book_copy', $copy, null, $copy->toArray());

        return back()->with('success', 'Eksemplar fisik berhasil ditambahkan.');
    }

    public function update(Request $request, BookCopy $bookCopy): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', 'in:available,borrowed,maintenance,lost'], 'condition' => ['required', 'in:good,minor_damage,damaged'], 'note' => ['nullable', 'string', 'max:1000']]);
        $before = $bookCopy->toArray();
        $bookCopy->update($data);
        ActivityLogger::write('update', 'book_copy', $bookCopy, $before, $bookCopy->fresh()->toArray());

        return back()->with('success', 'Status eksemplar diperbarui.');
    }
}
