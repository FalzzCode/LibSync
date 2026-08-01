<?php

namespace App\Http\Controllers;

use App\Http\Requests\BookRequest;
use App\Models\Book;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class BookController extends Controller
{
    // Menampilkan daftar buku, dengan fitur pencarian judul/penulis
    public function index(Request $request): View
    {
        $search = $request->query('search');
        $categoryId = $request->query('category');
        $status = $request->query('status');

        $books = Book::with('category')
            ->when($search, function ($query, $search) {
                $query->where('title', 'like', "%{$search}%")
                    ->orWhere('author', 'like', "%{$search}%");
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
                'cover' => $book->cover_image ? asset('storage/'.$book->cover_image) : null,
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

        if ($request->hasFile('cover_image')) {
            $data['cover_image'] = $request->file('cover_image')->store('books', 'public');
        }

        Book::create($data);

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

        if ($request->hasFile('cover_image')) {
            // Hapus cover lama biar file gak numpuk sia-sia di storage
            if ($book->cover_image) {
                Storage::disk('public')->delete($book->cover_image);
            }

            $data['cover_image'] = $request->file('cover_image')->store('books', 'public');
        }

        $book->update($data);

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

        if ($book->cover_image) {
            Storage::disk('public')->delete($book->cover_image);
        }

        $book->delete();

        return redirect()->route('books.index')->with('success', 'Buku berhasil dihapus.');
    }
}
