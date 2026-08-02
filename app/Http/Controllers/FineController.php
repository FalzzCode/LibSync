<?php

namespace App\Http\Controllers;

use App\Models\Fine;
use App\Models\FinePayment;
use App\Models\Member;
use App\Services\ActivityLogger;
use App\Services\MemberStanding;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class FineController extends Controller
{
    public function index(Request $request): View
    {
        $fines = Fine::with(['member', 'borrowing.book', 'payments.receiver'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = $request->string('search')->trim()->substr(0, 120)->toString();
                if ($term === '') {
                    return;
                }
                $query->where(function ($query) use ($term) {
                    $query->whereHas('member', fn ($member) => $member->where('name', 'like', "%{$term}%"))
                        ->orWhereHas('borrowing.book', fn ($book) => $book->where('title', 'like', "%{$term}%"));
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->status))
            ->latest()
            ->get();

        return view('fines.index', compact('fines'));
    }

    public function pay(Request $request, Fine $fine): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'method' => ['required', 'in:cash,transfer,qris,waived'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($fine, $data) {
            $fine = Fine::lockForUpdate()->findOrFail($fine->id);
            if ($fine->status === 'paid') {
                throw ValidationException::withMessages(['amount' => 'Denda ini sudah lunas.']);
            }
            if ($data['amount'] > $fine->balance) {
                throw ValidationException::withMessages(['amount' => 'Nominal pembayaran melebihi sisa denda.']);
            }

            FinePayment::create($data + [
                'fine_id' => $fine->id,
                'received_by' => auth()->id(),
                'paid_at' => now(),
            ]);
            $paid = $fine->paid_amount + $data['amount'];
            $fine->update(['paid_amount' => $paid, 'status' => $paid >= $fine->amount ? 'paid' : 'partial']);
            MemberStanding::refresh(Member::lockForUpdate()->findOrFail($fine->member_id));
            ActivityLogger::write('fine_payment', 'fine', $fine, null, ['amount' => $data['amount'], 'method' => $data['method']]);
        });

        return back()->with('success', 'Pembayaran denda berhasil dicatat.');
    }
}
