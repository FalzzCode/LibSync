<?php

namespace App\Http\Controllers;

use App\Models\BookCopy;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BookCopyController extends Controller
{
    public function index(Request $request): View
    {
        $copies = BookCopy::with('book:id,title,book_code')
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = $request->string('search')->trim();
                $query->where(fn ($q) => $q->where('inventory_code', 'like', "%{$term}%")->orWhere('barcode', 'like', "%{$term}%")->orWhereHas('book', fn ($book) => $book->where('title', 'like', "%{$term}%")));
            })->latest()->get();

        return view('book-copies.index', compact('copies'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'book_id' => ['required', 'exists:books,id'],
            'inventory_code' => ['required', 'string', 'max:100', 'unique:book_copies,inventory_code'],
            'barcode' => ['nullable', 'string', 'max:100', 'unique:book_copies,barcode'],
            'condition' => ['required', 'in:good,minor_damage,damaged'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);
        $copy = BookCopy::create($data);
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
