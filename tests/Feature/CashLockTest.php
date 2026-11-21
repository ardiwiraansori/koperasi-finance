<?php

namespace Tests\Feature;

use App\Models\JournalEntry;
use App\Models\Setting;
use App\Services\Ledger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// Kunci kas sungguhan hanya terlihat di database yang mendukung kunci baris (MariaDB, PostgreSQL); SQLite di tes tidak punya kunci.
// Yang bisa dijamin di sini: baris pengunci ada tepat satu, dan saldo yang dikembalikan benar.
class CashLockTest extends TestCase
{
    use RefreshDatabase;

    public function test_lock_returns_cash_balance_and_keeps_a_single_lock_row(): void
    {
        $j = JournalEntry::create(['journal_no' => 'JU-1', 'journal_date' => '2026-01-01', 'description' => 'Saldo awal', 'source' => 'AUTO JOURNAL', 'status' => 'Posted']);
        $j->lines()->create(['account_code' => '10101', 'account_name' => 'Kas Teller', 'debit' => 7500000, 'credit' => 0]);
        $j->lines()->create(['account_code' => '39101', 'account_name' => 'Ekuitas Saldo Awal', 'debit' => 0, 'credit' => 7500000]);

        DB::transaction(function () {
            $this->assertEquals(7500000, Ledger::lockCash());
            $this->assertEquals(7500000, Ledger::lockCash());   // pemanggilan kedua tidak membuat baris ganda
        });

        $this->assertSame(1, Setting::where('key', 'cash_lock')->count());
    }

    public function test_lock_row_does_not_leak_into_settings(): void
    {
        DB::transaction(fn () => Ledger::lockCash());
        $this->assertNull(Setting::get('cash_lock'));
        $this->assertSame('Koperasi Mutiara Tigo Pasie', Setting::cooperativeName());
    }
}
