<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Category;
use App\Models\Member;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DeveloperPanelController extends Controller
{
    private function guard(): void
    {
        abort_unless(
            app()->environment(['local', 'testing']) && (auth()->user()?->role === 'developer' || session()->has('developer_original_user_id')),
            404,
        );
    }

    public function index(): View
    {
        $this->guard();
        Artisan::call('migrate:status');
        $log = is_file(storage_path('logs/laravel.log')) ? collect(file(storage_path('logs/laravel.log')))->take(-24)->implode('') : 'Belum ada file log.';

        $testUsers = User::query()->whereIn('email', $this->testAccountEmails())->get()->keyBy('role');

        return view('developer.index', ['migrationStatus' => trim(Artisan::output()), 'log' => $log, 'testUsers' => $testUsers, 'checks' => ['Koneksi database' => config('database.default'), 'Tabel members' => Schema::hasTable('members') ? 'Siap' : 'Belum ada', 'Tabel borrowings' => Schema::hasTable('borrowings') ? 'Siap' : 'Belum ada', 'Storage publik' => file_exists(public_path('storage')) ? 'Siap' : 'Perlu dibuat']]);
    }

    public function seedDemo(): RedirectResponse
    {
        $this->guard();
        $category = Category::firstOrCreate(['name' => 'Koleksi Uji']);
        Book::firstOrCreate(['title' => 'Buku Uji Developer'], ['author' => 'Ruang Baca', 'category_id' => $category->id, 'stock' => 3]);
        Member::firstOrCreate(['phone' => '08000000001'], ['name' => 'Siswa Uji Developer', 'class' => 'XII Uji']);

        return back()->with('success', 'Data uji aman disiapkan atau sudah tersedia.');
    }

    public function runOverdueCheck(): RedirectResponse
    {
        $this->guard();
        Artisan::call('library:check-overdues');

        return back()->with('success', trim(Artisan::output()));
    }

    public function clearCache(): RedirectResponse
    {
        $this->guard();
        Artisan::call('optimize:clear');

        return back()->with('success', 'Cache aplikasi berhasil dibersihkan.');
    }

    public function prepareRoleTests(): RedirectResponse
    {
        $this->guard();
        $this->createTestAccounts();

        return back()->with('success', 'Akun uji Admin, Staff, dan Student sudah siap digunakan.');
    }

    public function switchRole(Request $request): RedirectResponse
    {
        $this->guard();
        $role = $request->validate(['role' => ['required', 'in:admin,staff,student']])['role'];
        $user = User::query()->where('email', 'developer.'.$role.'@libsync.test')->first();

        if (! $user) {
            return back()->with('error', 'Siapkan akun role uji terlebih dahulu.');
        }

        if (! $request->session()->has('developer_original_user_id')) {
            $request->session()->put('developer_original_user_id', auth()->id());
        }
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route($role === 'student' ? 'student.dashboard' : 'dashboard')
            ->with('success', 'Mode uji aktif: '.ucfirst($role).'.');
    }

    public function restoreOriginalUser(Request $request): RedirectResponse
    {
        $this->guard();
        $originalUser = User::find($request->session()->pull('developer_original_user_id'));

        if (! $originalUser) {
            return redirect()->route('login')->with('error', 'Sesi akun developer sudah berakhir.');
        }

        Auth::login($originalUser);
        $request->session()->regenerate();

        return redirect()->route('developer.index')->with('success', 'Kembali ke akun developer.');
    }

    private function createTestAccounts(): void
    {
        $accounts = [
            'admin' => ['name' => 'Admin Uji', 'email' => 'developer.admin@libsync.test'],
            'staff' => ['name' => 'Staff Uji', 'email' => 'developer.staff@libsync.test'],
            'student' => ['name' => 'Siswa Uji', 'email' => 'developer.student@libsync.test'],
        ];

        foreach ($accounts as $role => $account) {
            $user = User::firstOrCreate(['email' => $account['email']], [
                'name' => $account['name'],
                'password' => Hash::make(Str::random(48)),
                'role' => $role,
                'email_verified_at' => now(),
            ]);

            if ($role === 'student') {
                Member::firstOrCreate(['user_id' => $user->id], [
                    'name' => $account['name'],
                    'nis' => 'DEV-STUDENT-001',
                    'class' => 'XII Uji',
                    'email' => $account['email'],
                    'phone' => '08000000001',
                    'activated_at' => now(),
                ]);
            }
        }
    }

    private function testAccountEmails(): array
    {
        return ['developer.admin@libsync.test', 'developer.staff@libsync.test', 'developer.student@libsync.test'];
    }
}
