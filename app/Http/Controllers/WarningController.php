<?php

namespace App\Http\Controllers;

use App\Models\Warning;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WarningController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'level' => ['nullable', Rule::in(['critical', 'warning', 'info'])],
            'open' => ['nullable', 'boolean'],
        ]);
        $search = trim((string) ($filters['search'] ?? ''));
        $level = $filters['level'] ?? null;

        $warnings = Warning::with(['member', 'borrowing.book'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('message', 'like', "%{$search}%")
                        ->orWhereHas('member', fn ($member) => $member->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('borrowing.book', fn ($book) => $book->where('title', 'like', "%{$search}%"));
                });
            })
            ->when($level, fn ($query) => $query->where('level', $level))
            ->when($request->boolean('open'), fn ($query) => $query->whereNull('resolved_at'))
            ->latest()->get();

        return view('warnings.index', compact('warnings'));
    }

    public function resolve(Request $request, Warning $warning): RedirectResponse
    {
        $data = $request->validate(['resolution_note' => ['nullable', 'string', 'max:1000']]);
        DB::transaction(function () use ($warning, $data): void {
            $warning = Warning::query()->lockForUpdate()->findOrFail($warning->id);
            if ($warning->resolved_at) {
                return;
            }

            $warning->update(['resolved_at' => now(), 'resolution_note' => $data['resolution_note'] ?? null]);
            ActivityLogger::write('resolve', 'warning', $warning, null, ['resolved_at' => $warning->resolved_at]);
        });

        return back()->with('success', 'Peringatan ditandai selesai.');
    }
}
