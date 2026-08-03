<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserRequest;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class UserController extends Controller
{
    // Menampilkan daftar semua user
    public function index(Request $request): View
    {
        $this->ensureAdmin();
        $search = $request->string('search')->trim()->substr(0, 120)->toString();
        $users = User::query()
            ->whereIn('role', ['admin', 'staff'])
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('role', 'like', "%{$search}%");
            }))
            ->latest()
            ->get();

        return view('users.index', compact('users', 'search'));
    }

    // Menampilkan form tambah user
    public function create(): View
    {
        $this->ensureAdmin();

        return view('users.create');
    }

    // Menyimpan user baru
    public function store(UserRequest $request): RedirectResponse
    {
        $this->ensureAdmin();
        $user = User::create($request->validated());
        ActivityLogger::write('create', 'user', $user, null, $user->only(['id', 'name', 'email', 'role']));

        return redirect()->route('users.index')->with('success', 'Pengguna berhasil ditambahkan.');
    }

    // Menampilkan form edit user
    public function edit(User $user): View
    {
        $this->ensureAdmin();
        abort_if($user->role === 'student', 404);

        return view('users.edit', compact('user'));
    }

    // Memperbarui data user
    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $this->ensureAdmin();
        abort_if($user->role === 'student', 404);

        $data = $request->validated();
        $updateError = null;

        DB::transaction(function () use ($data, $user, &$updateError): void {
            // Lock all admin rows in a deterministic order before changing a
            // role. Two admins cannot concurrently demote/delete each other
            // and accidentally leave the system without an administrator.
            $admins = User::query()->where('role', 'admin')->orderBy('id')->lockForUpdate()->get(['id']);
            $user = User::query()->lockForUpdate()->findOrFail($user->id);

            if ($user->role === 'admin' && $data['role'] !== 'admin' && $admins->count() <= 1) {
                $updateError = 'Admin terakhir tidak dapat diubah perannya. Tambahkan admin lain terlebih dahulu.';

                return;
            }

            // Password dikosongkan di form = tidak diganti.
            $attributes = $data;
            if (empty($attributes['password'])) {
                unset($attributes['password']);
            }

            $before = $user->only(['id', 'name', 'email', 'role']);
            $user->update($attributes);
            ActivityLogger::write('update', 'user', $user, $before, $user->fresh()->only(['id', 'name', 'email', 'role']));
        });

        if ($updateError) {
            return back()->withInput()->with('error', $updateError);
        }

        return redirect()->route('users.index')->with('success', 'Pengguna berhasil diperbarui.');
    }

    // Menghapus user
    public function destroy(User $user): RedirectResponse
    {
        $this->ensureAdmin();
        if ($user->role === 'student') {
            return redirect()->route('members.index')->with('error', 'Akun siswa dikelola melalui data anggota.');
        }

        // Cegah admin menghapus akunnya sendiri saat sedang login (biar gak terkunci dari sistem)
        if ($user->id === auth()->id()) {
            return redirect()->route('users.index')->with('error', 'Anda tidak bisa menghapus akun Anda sendiri.');
        }

        $deleteError = null;
        DB::transaction(function () use ($user, &$deleteError): void {
            $admins = User::query()->where('role', 'admin')->orderBy('id')->lockForUpdate()->get(['id']);
            $user = User::query()->lockForUpdate()->findOrFail($user->id);

            if ($user->role === 'admin' && $admins->count() <= 1) {
                $deleteError = 'Admin terakhir tidak dapat dihapus. Tambahkan admin lain terlebih dahulu.';

                return;
            }
            if ($user->borrowings()->lockForUpdate()->exists()) {
                $deleteError = 'Pengguna tidak dapat dihapus karena tercatat pada riwayat transaksi.';

                return;
            }

            $before = $user->only(['id', 'name', 'email', 'role']);
            $user->delete();
            ActivityLogger::write('delete', 'user', $user, $before, null);
        });

        if ($deleteError) {
            return redirect()->route('users.index')->with('error', $deleteError);
        }

        return redirect()->route('users.index')->with('success', 'Pengguna berhasil dihapus.');
    }

    private function ensureAdmin(): void
    {
        abort_unless(auth()->user()?->role === 'admin', 403);
    }
}
