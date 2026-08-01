<?php

namespace App\Http\Controllers;

use App\Models\Warning;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WarningController extends Controller
{
    public function index(Request $request): View
    {
        $warnings = Warning::with(['member', 'borrowing.book'])
            ->when($request->filled('level'), fn ($query) => $query->where('level', $request->level))
            ->when($request->boolean('open'), fn ($query) => $query->whereNull('resolved_at'))
            ->latest()->get();

        return view('warnings.index', compact('warnings'));
    }

    public function resolve(Request $request, Warning $warning): RedirectResponse
    {
        $data = $request->validate(['resolution_note' => ['nullable', 'string', 'max:1000']]);
        if (! $warning->resolved_at) {
            $warning->update(['resolved_at' => now(), 'resolution_note' => $data['resolution_note'] ?? null]);
            ActivityLogger::write('resolve', 'warning', $warning, null, ['resolved_at' => $warning->resolved_at]);
        }

        return back()->with('success', 'Peringatan ditandai selesai.');
    }
}
