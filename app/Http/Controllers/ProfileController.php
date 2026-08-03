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
use Throwable;

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
            'email' => ['required', 'email', 'max:255', Rule::unique('pengguna', 'email')->ignore($user->id)],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];

        if ($user->role === 'student' && $linkedMember) {
            $rules['email'][] = Rule::unique('anggota', 'email')->ignore($linkedMember->id);
        }

        $data = $request->validate($rules);

        $previousPhotoPath = $user->profile_photo_path;
        $newPhotoPath = null;

        if ($request->hasFile('photo')) {
            $newPhotoPath = $request->file('photo')->store('profile-photos', 'public');
            $data['profile_photo_path'] = $newPhotoPath;
        }

        unset($data['photo']);
        try {
            DB::transaction(function () use ($user, $linkedMember, $data) {
                $user->update($data);

                if ($user->role === 'student' && $linkedMember) {
                    $linkedMember->update([
                        'name' => $data['name'],
                        'email' => $data['email'],
                    ]);
                }
            });
        } catch (Throwable $exception) {
            // The file is stored before the transaction so it can be served
            // after commit. Remove it when the database update fails to avoid
            // orphaned profile photos in long-lived storage.
            if ($newPhotoPath) {
                Storage::disk('public')->delete($newPhotoPath);
            }

            throw $exception;
        }

        if ($newPhotoPath && $previousPhotoPath && $previousPhotoPath !== $newPhotoPath) {
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
