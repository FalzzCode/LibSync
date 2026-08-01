<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class UserController extends Controller
{
    // Menampilkan daftar semua user
    public function index(): View
    {
        $users = User::whereIn('role', ['admin', 'staff'])->latest()->get();

        return view('users.index', compact('users'));
    }

    // Menampilkan form tambah user
    public function create(): View
    {
        return view('users.create');
    }

    // Menyimpan user baru
    public function store(UserRequest $request): RedirectResponse
    {
        User::create($request->validated());

        return redirect()->route('users.index')->with('success', 'User berhasil ditambahkan.');
    }

    // Menampilkan form edit user
    public function edit(User $user): View
    {
        abort_if($user->role === 'student', 404);

        return view('users.edit', compact('user'));
    }

    // Memperbarui data user
    public function update(UserRequest $request, User $user): RedirectResponse
    {
        abort_if($user->role === 'student', 404);

        $data = $request->validated();

        if ($user->role === 'admin' && $data['role'] !== 'admin' && User::where('role', 'admin')->count() <= 1) {
            return back()->withInput()->with('error', 'Admin terakhir tidak dapat diubah perannya. Tambahkan admin lain terlebih dahulu.');
        }

        // Password dikosongkan di form = tidak diganti
        if (empty($data['password'])) {
            unset($data['password']);
        }

        $user->update($data);

        return redirect()->route('users.index')->with('success', 'User berhasil diperbarui.');
    }

    // Menghapus user
    public function destroy(User $user): RedirectResponse
    {
        if ($user->role === 'student') {
            return redirect()->route('members.index')->with('error', 'Akun siswa dikelola melalui data anggota.');
        }

        // Cegah admin menghapus akunnya sendiri saat sedang login (biar gak terkunci dari sistem)
        if ($user->id === auth()->id()) {
            return redirect()->route('users.index')->with('error', 'Anda tidak bisa menghapus akun Anda sendiri.');
        }

        if ($user->borrowings()->exists()) {
            return redirect()->route('users.index')->with('error', 'User tidak dapat dihapus karena tercatat pada riwayat transaksi.');
        }

        $user->delete();

        return redirect()->route('users.index')->with('success', 'User berhasil dihapus.');
    }
}
