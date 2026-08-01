<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\BookController;
use App\Http\Controllers\BookCopyController;
use App\Http\Controllers\BorrowingController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeveloperPanelController;
use App\Http\Controllers\FineController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\StudentActivationController;
use App\Http\Controllers\StudentBorrowingController;
use App\Http\Controllers\StudentCatalogController;
use App\Http\Controllers\StudentDashboardController;
use App\Http\Controllers\StudentReservationController;
use App\Http\Controllers\SystemSettingController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WarningController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});
Route::view('/offline', 'offline')->name('offline');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
    Route::get('/auth/google', [AuthController::class, 'redirectToGoogle'])->name('auth.google.redirect');
    Route::get('/auth/google/callback', [AuthController::class, 'handleGoogleCallback'])->name('auth.google.callback');
    Route::get('/aktivasi-siswa', [StudentActivationController::class, 'create'])->name('student.activation.create');
    Route::post('/aktivasi-siswa', [StudentActivationController::class, 'store'])->name('student.activation.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::patch('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
    Route::get('/profile/photo/{user}', [ProfileController::class, 'photo'])->name('profile.photo');
    Route::get('developer', [DeveloperPanelController::class, 'index'])->name('developer.index');
    Route::post('developer/seed-demo', [DeveloperPanelController::class, 'seedDemo'])->name('developer.seed-demo');
    Route::post('developer/check-overdues', [DeveloperPanelController::class, 'runOverdueCheck'])->name('developer.check-overdues');
    Route::post('developer/clear-cache', [DeveloperPanelController::class, 'clearCache'])->name('developer.clear-cache');
    Route::post('developer/prepare-role-tests', [DeveloperPanelController::class, 'prepareRoleTests'])->name('developer.prepare-role-tests');
    Route::post('developer/switch-role', [DeveloperPanelController::class, 'switchRole'])->name('developer.switch-role');
    Route::post('developer/restore-user', [DeveloperPanelController::class, 'restoreOriginalUser'])->name('developer.restore-user');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::middleware('role:admin,staff')->group(function () {
        Route::resource('categories', CategoryController::class)->except(['show']);

        Route::resource('books', BookController::class);
        Route::get('book-copies', [BookCopyController::class, 'index'])->name('book-copies.index');
        Route::post('book-copies', [BookCopyController::class, 'store'])->name('book-copies.store');
        Route::patch('book-copies/{bookCopy}', [BookCopyController::class, 'update'])->name('book-copies.update');

        Route::resource('borrowings', BorrowingController::class)->only(['index', 'create', 'store', 'show']);
        Route::post('borrowings/{borrowing}/return', [BorrowingController::class, 'returnBook'])->name('borrowings.return');
        Route::post('borrowings/{borrowing}/approve', [BorrowingController::class, 'approve'])->name('borrowings.approve');
        Route::post('borrowings/{borrowing}/approve-extension', [BorrowingController::class, 'approveExtension'])->name('borrowings.approve-extension');

        Route::get('warnings', [WarningController::class, 'index'])->name('warnings.index');
        Route::post('warnings/{warning}/resolve', [WarningController::class, 'resolve'])->name('warnings.resolve');
        Route::get('fines', [FineController::class, 'index'])->name('fines.index');
        Route::post('fines/{fine}/pay', [FineController::class, 'pay'])->name('fines.pay');
        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('reports/borrowings.csv', [ReportController::class, 'borrowingsCsv'])->name('reports.borrowings.csv');
        Route::get('reports/fine-payments.csv', [ReportController::class, 'finePaymentsCsv'])->name('reports.fine-payments.csv');
        Route::get('imports', [ImportController::class, 'create'])->name('imports.create');
        Route::post('imports', [ImportController::class, 'store'])->name('imports.store');

        Route::resource('members', MemberController::class)->except(['show']);
        Route::post('members/{member}/activation-code', [MemberController::class, 'regenerateActivationCode'])->name('members.activation-code');
    });

    Route::middleware('role:student')->group(function () {
        Route::get('student/dashboard', [StudentDashboardController::class, 'index'])->name('student.dashboard');
        Route::get('student/catalog', [StudentCatalogController::class, 'index'])->name('student.catalog');
        Route::post('student/books/{book}/borrow', [StudentBorrowingController::class, 'store'])->name('student.borrowings.store');
        Route::post('student/books/{book}/reserve', [StudentReservationController::class, 'store'])->name('student.reservations.store');
        Route::post('student/borrowings/{borrowing}/return-request', [StudentBorrowingController::class, 'requestReturn'])->name('student.borrowings.return-request');
        Route::post('student/borrowings/{borrowing}/extension-request', [StudentBorrowingController::class, 'requestExtension'])->name('student.borrowings.extension-request');
    });

    Route::middleware('role:admin')->group(function () {
        Route::resource('users', UserController::class)->except(['show']);
        Route::get('settings', [SystemSettingController::class, 'edit'])->name('settings.edit');
        Route::patch('settings', [SystemSettingController::class, 'update'])->name('settings.update');
        Route::get('backups/download', [BackupController::class, 'download'])->name('backups.download');
    });
});
