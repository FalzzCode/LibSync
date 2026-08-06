<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Borrowing;
use App\Models\Member;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StudentCatalogController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:120']]);
        $search = trim((string) ($filters['search'] ?? ''));
        $books = Book::query()->with('category')->whereNull('archived_at')
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query->where('title', 'like', '%'.$search.'%')->orWhere('author', 'like', '%'.$search.'%')))
            ->orderBy('title')->get();
        $member = Member::query()->where('user_id', auth()->id())->firstOrFail();
        $activeBorrowingsByBook = Borrowing::query()
            ->where('member_id', $member->id)
            ->whereIn('status', ['requested', 'borrowed', 'return_requested'])
            ->pluck('status', 'book_id');

        return view('student.catalog', compact('books', 'search', 'activeBorrowingsByBook'));
    }
}
