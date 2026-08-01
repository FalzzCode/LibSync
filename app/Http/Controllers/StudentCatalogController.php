<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StudentCatalogController extends Controller
{
    public function index(Request $request): View
    {
        $books = Book::query()->with('category')->whereNull('archived_at')
            ->when($request->filled('search'), fn ($query) => $query->where(fn ($query) => $query->where('title', 'like', '%'.$request->search.'%')->orWhere('author', 'like', '%'.$request->search.'%')))
            ->orderBy('title')->get();

        return view('student.catalog', compact('books'));
    }
}
