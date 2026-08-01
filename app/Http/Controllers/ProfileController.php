<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('profile.edit', ['user' => $request->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $linkedMember = $user->member;
        $rules = [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];

        if ($user->role === 'student' && $linkedMember) {
            $rules['email'][] = Rule::unique('members', 'email')->ignore($linkedMember->id);
        }

        $data = $request->validate($rules);

        $previousPhotoPath = $user->profile_photo_path;

        if ($request->hasFile('photo')) {
            $data['profile_photo_path'] = $request->file('photo')->store('profile-photos', 'public');
        }

        unset($data['photo']);
        DB::transaction(function () use ($user, $linkedMember, $data) {
            $user->update($data);

            if ($user->role === 'student' && $linkedMember) {
                $linkedMember->update([
                    'name' => $data['name'],
                    'email' => $data['email'],
                ]);
            }
        });

        if (isset($data['profile_photo_path']) && $previousPhotoPath && $previousPhotoPath !== $data['profile_photo_path']) {
            Storage::disk('public')->delete($previousPhotoPath);
        }

        return back()->with('success', 'Profil berhasil diperbarui.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'current_password.current_password' => 'Password saat ini tidak sesuai.',
        ]);

        $request->user()->forceFill(['password' => Hash::make($data['password'])])->save();

        return back()->with('success', 'Password berhasil diperbarui.');
    }

    public function photo(Request $request, User $user): StreamedResponse
    {
        abort_unless($request->user()->id === $user->id || $request->user()->role === 'admin', 403);
        abort_unless($user->profile_photo_path && Storage::disk('public')->exists($user->profile_photo_path), 404);

        return Storage::disk('public')->response($user->profile_photo_path, headers: [
            'Cache-Control' => 'private, no-cache, max-age=0, must-revalidate',
        ]);
    }
}
