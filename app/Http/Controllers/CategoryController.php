<?php

namespace App\Http\Controllers;

use App\Http\Requests\CategoryRequest;
use App\Models\Category;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CategoryController extends Controller
{
    // Menampilkan daftar semua kategori
    public function index(Request $request): View
    {
        $search = $request->string('search')->trim()->substr(0, 120)->toString();
        $categories = Category::query()
            ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ->latest()
            ->get();

        return view('categories.index', compact('categories', 'search'));
    }

    // Menampilkan form tambah kategori
    public function create(): View
    {
        return view('categories.create');
    }

    // Menyimpan kategori baru
    public function store(CategoryRequest $request): RedirectResponse
    {
        $category = Category::create($request->validated());
        ActivityLogger::write('create', 'category', $category, null, $category->toArray());

        return redirect()->route('categories.index')->with('success', 'Kategori berhasil ditambahkan.');
    }

    // Menampilkan form edit kategori
    public function edit(Category $category): View
    {
        return view('categories.edit', compact('category'));
    }

    // Memperbarui kategori
    public function update(CategoryRequest $request, Category $category): RedirectResponse
    {
        $before = $category->toArray();
        $category->update($request->validated());
        ActivityLogger::write('update', 'category', $category, $before, $category->fresh()->toArray());

        return redirect()->route('categories.index')->with('success', 'Kategori berhasil diperbarui.');
    }

    // Menghapus kategori
    public function destroy(Category $category): RedirectResponse
    {
        if ($category->books()->exists()) {
            return back()->with('error', 'Kategori yang masih memiliki buku tidak dapat dihapus.');
        }

        $before = $category->toArray();
        $category->delete();
        ActivityLogger::write('delete', 'category', $category, $before, null);

        return redirect()->route('categories.index')->with('success', 'Kategori berhasil dihapus.');
    }
}
