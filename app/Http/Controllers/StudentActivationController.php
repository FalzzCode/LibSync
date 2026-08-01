<?php

namespace App\Http\Controllers;

use App\Http\Requests\StudentActivationRequest;
use App\Services\StudentPortalActivation;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class StudentActivationController extends Controller
{
    public function create(): View
    {
        return view('auth.activate');
    }

    public function store(StudentActivationRequest $request): RedirectResponse
    {
        $member = StudentPortalActivation::findEligibleMember(
            $request->string('nis')->toString(),
            $request->string('activation_code')->toString(),
        );

        if (! $member) {
            return back()
                ->withErrors(['activation_code' => 'NIS atau kode aktivasi tidak valid, sudah digunakan, atau telah kedaluwarsa.'])
                ->onlyInput('nis');
        }

        $request->session()->put('student_activation_member_id', $member->id);

        return redirect()->route('auth.google.redirect');
    }
}
