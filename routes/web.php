<?php

use App\Http\Controllers\AccountingController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SavingsController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\ShuController;
use App\Http\Controllers\TellerController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:5,1')->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
    // Semua akun (petugas dan anggota) boleh mengganti sandinya sendiri; sandi lama dibatasi 6 percobaan per menit.
    Route::get('/kata-sandi', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('/kata-sandi', [PasswordController::class, 'update'])->middleware('throttle:6,1')->name('password.update');
    Route::get('/portal', [PortalController::class, 'index'])->middleware('area:portal')->name('portal.home');

    // Semua halaman di bawah ini khusus petugas; akun anggota dialihkan ke /portal.
    Route::middleware('area:staff')->group(function () {
    Route::redirect('/', '/dashboard');

    // Semua peran: melihat data dan mencari.
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/cari', [SearchController::class, 'index'])->name('search');
    Route::get('/anggota', [MemberController::class, 'index'])->name('members.index');
    Route::get('/simpanan', [SavingsController::class, 'index'])->name('savings.index');
    Route::get('/simpanan/rekening-koran', [SavingsController::class, 'statement'])->name('savings.statement');
    Route::get('/pinjaman', [LoanController::class, 'index'])->name('loans.index');

    // Kasir dan admin: menginput.
    Route::middleware('ability:members.write')->group(function () {
        Route::get('/anggota/tambah', [MemberController::class, 'create'])->name('members.create');
        Route::post('/anggota', [MemberController::class, 'store'])->name('members.store');
        Route::post('/anggota/{member}/akun', [MemberController::class, 'portalAccount'])->name('members.account');
        Route::get('/anggota/{member}/ubah', [MemberController::class, 'edit'])->name('members.edit');
        Route::put('/anggota/{member}', [MemberController::class, 'update'])->name('members.update');
    });
    Route::middleware('ability:savings.tx')->group(function () {
        Route::get('/simpanan/setoran', [SavingsController::class, 'create'])->defaults('mode', 'setoran')->name('savings.deposit');
        Route::post('/simpanan/setoran', [SavingsController::class, 'store'])->defaults('mode', 'setoran')->name('savings.deposit.store');
        Route::get('/simpanan/penarikan', [SavingsController::class, 'create'])->defaults('mode', 'penarikan')->name('savings.withdraw');
        Route::post('/simpanan/penarikan', [SavingsController::class, 'store'])->defaults('mode', 'penarikan')->name('savings.withdraw.store');
    });
    Route::middleware('ability:loan.apply')->group(function () {
        Route::get('/pinjaman/ajukan', [LoanController::class, 'create'])->name('loans.create');
        Route::post('/pinjaman', [LoanController::class, 'store'])->name('loans.store');
    });
    Route::middleware('ability:cash')->group(function () {
        Route::get('/kasir', [TellerController::class, 'index'])->name('teller.index');
        Route::post('/kasir', [TellerController::class, 'store'])->name('teller.store');
    });

    // Saldo awal (go-live): admin dan akuntan.
    Route::middleware('ability:opening')->group(function () {
        Route::get('/simpanan/saldo-awal', [SavingsController::class, 'openingForm'])->name('savings.opening');
        Route::post('/simpanan/saldo-awal', [SavingsController::class, 'openingStore'])->name('savings.opening.store');
        Route::get('/pinjaman/saldo-awal', [LoanController::class, 'openingForm'])->name('loans.opening');
        Route::post('/pinjaman/saldo-awal', [LoanController::class, 'openingStore'])->name('loans.opening.store');
    });

    Route::get('/anggota/export', [MemberController::class, 'export'])->middleware('ability:export')->name('members.export');
    Route::get('/anggota/{member}', [MemberController::class, 'show'])->name('members.show');
    Route::get('/anggota/{member}/formulir', [MemberController::class, 'sheet'])->name('members.sheet');
    Route::get('/pinjaman/{loan}', [LoanController::class, 'show'])->name('loans.show');

    // Keputusan: admin / pengurus. Pencairan uangnya oleh kasir setelah disetujui.
    Route::middleware('ability:loan.decide')->group(function () {
        Route::post('/pinjaman/{loan}/setujui', [LoanController::class, 'approve'])->name('loans.approve');
        Route::post('/pinjaman/{loan}/tolak', [LoanController::class, 'reject'])->name('loans.reject');
        Route::get('/approval', [LoanController::class, 'approvals'])->name('approval.index');
    });
    Route::middleware('ability:loan.disburse')->group(function () {
        Route::get('/pencairan', [LoanController::class, 'disbursements'])->name('disbursement.index');
        Route::post('/pinjaman/{loan}/cairkan', [LoanController::class, 'disburse'])->name('loans.disburse');
    });

    // Akuntansi, laporan, SHU: admin dan akuntan.
    Route::middleware('ability:accounting')->group(function () {
        Route::get('/akuntansi', [AccountingController::class, 'index'])->name('accounting.index');
        Route::get('/akuntansi/export', [AccountingController::class, 'export'])->name('accounting.export');
        Route::get('/akuntansi/buku-besar', [AccountingController::class, 'ledger'])->name('accounting.ledger');
        Route::get('/akuntansi/neraca-saldo', [AccountingController::class, 'trialBalance'])->name('accounting.trial');
        Route::get('/akuntansi/jenis-transaksi', [AccountingController::class, 'types'])->name('accounting.types');
        Route::get('/shu', [ShuController::class, 'index'])->name('shu.index');
    });
    // Laporan keuangan: hanya baca, boleh dilihat kasir juga.
    Route::middleware('ability:reports')->group(function () {
        Route::get('/laporan', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/laporan/neraca', [ReportController::class, 'balanceSheet'])->name('reports.balance');
        Route::get('/laporan/laba-rugi', [ReportController::class, 'incomeStatement'])->name('reports.income');
        Route::get('/laporan/arus-kas', [ReportController::class, 'cashFlow'])->name('reports.cashflow');
    });
    Route::middleware('ability:types.edit')->group(function () {
        Route::post('/akuntansi/jenis-transaksi', [AccountingController::class, 'storeType'])->name('accounting.types.store');
        Route::put('/akuntansi/jenis-transaksi/{type}', [AccountingController::class, 'updateType'])->name('accounting.types.update');
    });
    // Jurnal manual hanya bila koperasi memiliki akuntan (Pengaturan).
    Route::middleware('ability:journal.manual')->group(function () {
        Route::get('/akuntansi/jurnal-manual', [AccountingController::class, 'createManual'])->name('accounting.manual');
        Route::post('/akuntansi/jurnal-manual', [AccountingController::class, 'storeManual'])->name('accounting.manual.store');
    });

    Route::middleware('ability:settings')->group(function () {
        Route::get('/pengaturan', [SettingsController::class, 'index'])->name('settings.index');
        Route::put('/pengaturan', [SettingsController::class, 'update'])->name('settings.update');
        Route::post('/pengaturan/pengguna', [SettingsController::class, 'storeUser'])->name('settings.users.store');
        Route::put('/pengaturan/pengguna/{user}', [SettingsController::class, 'updateUser'])->name('settings.users.update');
        Route::delete('/pengaturan/pengguna/{user}', [SettingsController::class, 'destroyUser'])->name('settings.users.destroy');
    });
    });
});
