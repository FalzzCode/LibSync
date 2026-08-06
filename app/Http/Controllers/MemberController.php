<?php

namespace App\Http\Controllers;

use App\Http\Requests\MemberRequest;
use App\Models\Member;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\StudentPortalActivation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MemberController extends Controller
{
    // Menampilkan daftar semua anggota
    public function index(Request $request): View
    {
        $search = $request->string('search')->trim()->substr(0, 120)->toString();
        $members = Member::with('user:id,name,email,profile_photo_path,avatar_url,updated_at')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('class', 'like', "%{$search}%")
                        ->orWhere('major', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->get();

        return view('members.index', compact('members', 'search'));
    }

    // Menampilkan form tambah anggota
    public function create(): View
    {
        return view('members.create');
    }

    // Menampilkan anggota yang sudah diarsipkan agar admin atau petugas dapat memulihkannya.
    public function archived(): View
    {
        $members = Member::onlyTrashed()
            ->with('user:id,name,email')
            ->latest('deleted_at')
            ->get();

        return view('members.archived', compact('members'));
    }

    // Menyimpan anggota baru
    public function store(MemberRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $activationCode = null;
        $activationMember = null;
        $restoredMember = null;

        DB::transaction(function () use ($data, &$activationCode, &$activationMember, &$restoredMember) {
            $archivedMember = ! empty($data['email'])
                ? Member::withTrashed()
                    ->where('email', $data['email'])
                    ->lockForUpdate()
                    ->first()
                : null;

            if ($archivedMember?->trashed()) {
                $before = $archivedMember->toArray();
                $archivedMember->restore();
                $archivedMember->update(collect($data)->except(['account_email', 'account_password'])->all());

                if (! $archivedMember->user_id && ($data['account_email'] ?? null)) {
                    $archivedMember->update(['user_id' => $this->createStudentAccount($data)?->id]);
                }

                $restoredMember = $archivedMember->fresh();
                ActivityLogger::write('restore', 'member', $restoredMember, $before, $restoredMember->toArray());

                return;
            }

            $user = $this->createStudentAccount($data);
            $member = Member::create(collect($data)->except(['account_email', 'account_password'])->all() + ['user_id' => $user?->id]);
            if (! $user && $member->nis) {
                $activationCode = StudentPortalActivation::issue($member);
                $activationMember = $member;
            }
            ActivityLogger::write('create', 'member', $member, null, $member->toArray());
        });

        if ($restoredMember) {
            return redirect()->route('members.index')->with('success', "Anggota {$restoredMember->name} berhasil dipulihkan.");
        }

        $response = redirect()->route('members.index')->with('success', 'Anggota berhasil ditambahkan.');

        return $activationCode
            ? $response->with('activation_code', $activationCode)->with('activation_member_name', $activationMember->name)->with('activation_expires_at', $activationMember->activation_expires_at)
            : $response;
    }

    // Memulihkan anggota yang diarsipkan. Admin dan petugas sama-sama boleh melakukannya.
    public function restore(Member $member): RedirectResponse
    {
        if (! $member->trashed()) {
            return redirect()->route('members.index')->with('error', 'Anggota tersebut sudah aktif.');
        }

        $before = $member->toArray();
        $member->restore();
        ActivityLogger::write('restore', 'member', $member, $before, $member->fresh()->toArray());

        return redirect()->route('members.index')->with('success', "Anggota {$member->name} berhasil dipulihkan.");
    }

    // Menampilkan form edit anggota
    public function edit(Member $member): View
    {
        return view('members.edit', compact('member'));
    }

    // Memperbarui data anggota
    public function update(MemberRequest $request, Member $member): RedirectResponse
    {
        $data = $request->validated();
        $memberId = $member->id;
        DB::transaction(function () use ($data, $memberId) {
            $member = Member::query()->lockForUpdate()->findOrFail($memberId);
            $linkedUser = $member->user_id
                ? User::query()->lockForUpdate()->find($member->user_id)
                : null;
            $before = $member->toArray();
            if ($linkedUser && ($data['account_email'] ?? null || $data['account_password'] ?? null)) {
                $linkedUser->update(array_filter(['email' => $data['account_email'] ?? null, 'password' => $data['account_password'] ?? null]));
            } elseif (! $linkedUser && ($data['account_email'] ?? null)) {
                $member->update(['user_id' => $this->createStudentAccount($data)?->id]);
            }
            $member->update(collect($data)->except(['account_email', 'account_password'])->all());
            ActivityLogger::write('update', 'member', $member, $before, $member->fresh()->toArray());
        });

        return redirect()->route('members.index')->with('success', 'Anggota berhasil diperbarui.');
    }

    private function createStudentAccount(array $data): ?User
    {
        if (! ($data['account_email'] ?? null)) {
            return null;
        }

        return User::create(['name' => $data['name'], 'email' => $data['account_email'], 'password' => $data['account_password'], 'role' => 'student']);
    }

    public function regenerateActivationCode(Member $member): RedirectResponse
    {
        if ($member->user) {
            return back()->with('error', 'Akun portal siswa ini sudah aktif dan tidak memerlukan kode aktivasi.');
        }
        if (! $member->nis) {
            return back()->with('error', 'Isi NIS anggota terlebih dahulu sebelum membuat kode aktivasi.');
        }

        $code = StudentPortalActivation::issue($member);
        ActivityLogger::write('regenerate_activation_code', 'member', $member, null, ['expires_at' => $member->activation_expires_at]);

        return back()
            ->with('success', 'Kode aktivasi baru berhasil dibuat.')
            ->with('activation_code', $code)
            ->with('activation_member_name', $member->name)
            ->with('activation_expires_at', $member->activation_expires_at);
    }

    // Menghapus anggota
    public function destroy(Member $member): RedirectResponse
    {
        $deleteError = null;
        $memberId = $member->id;
        $userId = $member->user_id;
        DB::transaction(function () use ($memberId, $userId, &$deleteError): void {
            // User -> member is the same order used by profile and Google
            // identity updates. Lock first, delete only after all relations
            // have been checked.
            $portalUser = $userId ? User::query()->lockForUpdate()->find($userId) : null;
            $member = Member::query()->lockForUpdate()->findOrFail($memberId);
            if ($member->user_id && (! $portalUser || $portalUser->id !== $member->user_id)) {
                $portalUser = User::query()->lockForUpdate()->find($member->user_id);
            }
            if ($member->borrowings()->lockForUpdate()->exists()) {
                $deleteError = 'Anggota tidak dapat dihapus karena sudah memiliki riwayat transaksi.';

                return;
            }
            if ($member->reservations()->lockForUpdate()->exists()) {
                $deleteError = 'Anggota tidak dapat dihapus karena masih memiliki riwayat atau antrean buku.';

                return;
            }
            if ($member->fines()->lockForUpdate()->exists()) {
                $deleteError = 'Anggota tidak dapat dihapus karena masih memiliki catatan denda.';

                return;
            }

            $before = $member->toArray();
            $member->delete();

            // Akun portal hanya bermakna bila masih terhubung ke anggota aktif.
            if ($portalUser?->role === 'student') {
                $portalUser->delete();
            }
            ActivityLogger::write('delete', 'member', $member, $before, null);
        });

        if ($deleteError) {
            return back()->with('error', $deleteError);
        }

        return redirect()->route('members.index')->with('success', 'Anggota dan akun portalnya berhasil dihapus.');
    }
}
