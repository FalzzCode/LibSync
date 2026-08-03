<?php

namespace App\Http\Controllers;

use App\Http\Requests\BookRequest;
use App\Models\Book;
use App\Models\Category;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class BookController extends Controller
{
    // Menampilkan daftar buku, dengan pencarian sesuai data yang tampil di koleksi
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'integer', 'exists:kategori,id'],
            'status' => ['nullable', Rule::in(['tersedia', 'habis'])],
        ]);
        $search = trim((string) ($filters['search'] ?? ''));
        $categoryId = $filters['category'] ?? null;
        $status = $filters['status'] ?? null;

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
            if (! is_string($newCover) || $newCover === '') {
                throw ValidationException::withMessages(['cover_image' => 'Cover buku gagal disimpan. Periksa penyimpanan lalu coba lagi.']);
            }
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
            if (! is_string($newCover) || $newCover === '') {
                throw ValidationException::withMessages(['cover_image' => 'Cover buku gagal disimpan. Periksa penyimpanan lalu coba lagi.']);
            }
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
        $deleteError = null;
        $coverPath = null;
        DB::transaction(function () use ($book, &$deleteError, &$coverPath): void {
            $book = Book::query()->lockForUpdate()->findOrFail($book->id);
            if ($book->borrowings()->lockForUpdate()->exists()) {
                $deleteError = 'Buku tidak dapat dihapus karena sudah memiliki riwayat transaksi.';

                return;
            }

            if ($book->copies()->lockForUpdate()->exists()) {
                $deleteError = 'Buku tidak dapat dihapus karena masih memiliki salinan fisik. Hapus salinan atau arsipkan buku terlebih dahulu.';

                return;
            }

            if ($book->reservations()->lockForUpdate()->exists()) {
                $deleteError = 'Buku tidak dapat dihapus karena masih memiliki data antrean. Arsipkan buku atau selesaikan antreannya terlebih dahulu.';

                return;
            }

            $before = $book->toArray();
            $coverPath = $book->cover_image;
            $book->delete();
            ActivityLogger::write('delete', 'book', $book, $before, null);
        });

        if ($deleteError) {
            return back()->with('error', $deleteError);
        }

        // Remove the file only after the database transaction succeeds. If a
        // concurrent relation blocks deletion, the existing cover remains
        // usable instead of becoming an orphaned data loss.
        if ($coverPath) {
            Storage::disk('public')->delete($coverPath);
        }

        return redirect()->route('books.index')->with('success', 'Buku berhasil dihapus.');
    }
}
