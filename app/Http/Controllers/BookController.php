<?php

namespace App\Http\Controllers;

use App\Http\Requests\BookRequest;
use App\Models\Book;
use App\Models\Category;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Throwable;

class BookController extends Controller
{
    // Menampilkan daftar buku, dengan pencarian sesuai data yang tampil di koleksi
    public function index(Request $request): View
    {
        $search = $request->string('search')->trim()->substr(0, 120)->toString();
        $categoryId = $request->query('category');
        $status = $request->query('status');

        $books = Book::with('category')
            ->when(filled($search), function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('author', 'like', "%{$search}%")
                        ->orWhere('book_code', 'like', "%{$search}%")
                        ->orWhereHas('category', fn ($category) => $category->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($categoryId, function ($query, $categoryId) {
                $query->where('category_id', $categoryId);
            })
            ->when($status === 'tersedia', function ($query) {
                $query->where('stock', '>', 0);
            })
            ->when($status === 'habis', function ($query) {
                $query->where('stock', 0);
            })
            ->latest()
            ->get();

        $categories = Category::orderBy('name')->get();

        $booksForCatalog = $books->map(function ($book) {
            return [
                'id' => $book->id,
                'title' => $book->title,
                'author' => $book->author,
                'publisher' => $book->publisher,
                'year' => $book->publication_year,
                'category' => $book->category->name,
                'stock' => $book->stock,
                'available' => $book->stock,
                'cover' => $book->coverUrl(),
            ];
        });

        return view('books.index', compact('books', 'search', 'categoryId', 'status', 'categories', 'booksForCatalog'));
    }

    // Menampilkan detail satu buku
    public function show(Book $book): View
    {
        return view('books.show', compact('book'));
    }

    // Menampilkan form tambah buku
    public function create(): View
    {
        $categories = Category::orderBy('name')->get();

        return view('books.create', compact('categories'));
    }

    // Menyimpan buku baru
    public function store(BookRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $newCover = null;

        if ($request->hasFile('cover_image')) {
            $newCover = $request->file('cover_image')->store('books', 'public');
            $data['cover_image'] = $newCover;
        }

        try {
            $book = Book::create($data);
        } catch (Throwable $exception) {
            if ($newCover) {
                Storage::disk('public')->delete($newCover);
            }

            throw $exception;
        }
        ActivityLogger::write('create', 'book', $book, null, $book->toArray());

        return redirect()->route('books.index')->with('success', 'Buku berhasil ditambahkan.');
    }

    // Menampilkan form edit buku
    public function edit(Book $book): View
    {
        $categories = Category::orderBy('name')->get();

        return view('books.edit', compact('book', 'categories'));
    }

    // Memperbarui data buku
    public function update(BookRequest $request, Book $book): RedirectResponse
    {
        $data = $request->validated();
        $previousCover = $book->cover_image;
        $newCover = null;

        if ($request->hasFile('cover_image')) {
            // Simpan cover baru terlebih dahulu. Cover lama baru dihapus
            // setelah update database berhasil agar kegagalan upload/update
            // tidak membuat buku kehilangan cover yang masih valid.
            $newCover = $request->file('cover_image')->store('books', 'public');
            $data['cover_image'] = $newCover;
        }

        $before = $book->toArray();
        try {
            $book->update($data);
        } catch (Throwable $exception) {
            if ($newCover) {
                Storage::disk('public')->delete($newCover);
            }

            throw $exception;
        }

        if ($newCover && $previousCover && $previousCover !== $newCover) {
            Storage::disk('public')->delete($previousCover);
        }
        ActivityLogger::write('update', 'book', $book, $before, $book->fresh()->toArray());

        return redirect()->route('books.index')->with('success', 'Buku berhasil diperbarui.');
    }

    // Menghapus buku
    public function destroy(Book $book): RedirectResponse
    {
        if ($book->borrowings()->exists()) {
            return back()->with('error', 'Buku tidak dapat dihapus karena sudah memiliki riwayat transaksi.');
        }

        if ($book->reservations()->exists()) {
            return back()->with('error', 'Buku tidak dapat dihapus karena masih memiliki data antrean. Arsipkan buku atau selesaikan antreannya terlebih dahulu.');
        }

        $before = $book->toArray();
        if ($book->cover_image) {
            Storage::disk('public')->delete($book->cover_image);
        }

        $book->delete();
        ActivityLogger::write('delete', 'book', $book, $before, null);

        return redirect()->route('books.index')->with('success', 'Buku berhasil dihapus.');
    }
}
