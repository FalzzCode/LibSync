<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ProfileController extends Controller
{
    private const PHOTO_DISK = 'private';

    public function edit(Request $request): View
    {
        return view('profile.edit', ['user' => $request->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->merge([
            'name' => trim((string) $request->input('name')),
            'email' => strtolower(trim((string) $request->input('email'))),
        ]);
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
            $newPhotoPath = $request->file('photo')->store('profile-photos', self::PHOTO_DISK);
            if (! is_string($newPhotoPath) || $newPhotoPath === '') {
                throw ValidationException::withMessages(['photo' => 'Foto profil gagal disimpan. Periksa penyimpanan lalu coba lagi.']);
            }
            $data['profile_photo_path'] = $newPhotoPath;
        }

        unset($data['photo']);
        try {
            DB::transaction(function () use ($user, $linkedMember, $data) {
                // Lock user before its linked member, matching OAuth account
                // linking and member deletion to keep concurrent identity
                // changes in one deterministic order.
                $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
                $lockedMember = $linkedMember
                    ? Member::query()->lockForUpdate()->find($linkedMember->id)
                    : null;
                $lockedUser->update($data);

                if ($lockedUser->role === 'student' && $lockedMember) {
                    $lockedMember->update([
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
                Storage::disk(self::PHOTO_DISK)->delete($newPhotoPath);
            }

            throw $exception;
        }

        if ($newPhotoPath && $previousPhotoPath && $previousPhotoPath !== $newPhotoPath) {
            $this->deletePhoto($previousPhotoPath);
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
        $path = $this->normalizePhotoPath($user->profile_photo_path);
        $disk = $this->photoDisk($path);
        abort_unless($path && $disk, 404);

        return Storage::disk($disk)->response($path, headers: [
            'Cache-Control' => 'private, no-cache, max-age=0, must-revalidate',
        ]);
    }

    private function photoDisk(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        return Storage::disk(self::PHOTO_DISK)->exists($path) ? self::PHOTO_DISK : null;
    }

    private function deletePhoto(string $path): void
    {
        $path = $this->normalizePhotoPath($path);
        if (! $path) {
            return;
        }

        Storage::disk(self::PHOTO_DISK)->delete($path);
        Storage::disk('public')->delete($path);
    }

    private function normalizePhotoPath(?string $path): ?string
    {
        $path = str_replace('\\', '/', ltrim((string) $path, '/'));

        return $path !== '' && str_starts_with($path, 'profile-photos/') && ! str_contains($path, '..')
            ? $path
            : null;
    }
}
