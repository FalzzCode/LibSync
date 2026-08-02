<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StudentCatalogController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->string('search')->trim()->substr(0, 120)->toString();
        $books = Book::query()->with('category')->whereNull('archived_at')
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query->where('title', 'like', '%'.$search.'%')->orWhere('author', 'like', '%'.$search.'%')))
            ->orderBy('title')->get();

        return view('student.catalog', compact('books', 'search'));
    }
}
