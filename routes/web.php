<?php

use App\Http\Controllers\AccountingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\SavingsController;
use App\Http\Controllers\TellerController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');
Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/anggota', [MemberController::class, 'index'])->name('members.index');
Route::get('/anggota/{member}', [MemberController::class, 'show'])->name('members.show');
Route::get('/simpanan', [SavingsController::class, 'index'])->name('savings.index');
Route::get('/pinjaman', [LoanController::class, 'index'])->name('loans.index');
Route::get('/kasir', [TellerController::class, 'index'])->name('teller.index');
Route::post('/kasir', [TellerController::class, 'store'])->name('teller.store');
Route::get('/akuntansi', [AccountingController::class, 'index'])->name('accounting.index');
Route::view('/laporan', 'reports.index')->name('reports.index');
Route::view('/approval', 'approval.index')->name('approval.index');
Route::view('/shu', 'shu.index')->name('shu.index');
Route::view('/pengaturan', 'settings.index')->name('settings.index');
