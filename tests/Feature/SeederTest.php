<?php

namespace Tests\Feature;

use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Saving;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Reports;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_data_is_consistent_every_transaction_has_a_balanced_journal_and_savings_are_linked(): void
    {
        $this->seed(DatabaseSeeder::class);

        foreach (Transaction::all() as $t) {
            $journal = JournalEntry::with('lines')->where('reference', $t->trx_no)->first();
            $this->assertNotNull($journal, "{$t->trx_no} tidak punya jurnal");
            $this->assertEquals($journal->lines->sum('debit'), $journal->lines->sum('credit'), "jurnal {$t->trx_no} tidak seimbang");

            $kas = $journal->lines->firstWhere('account_code', '10101');
            if ($t->category === 'Saldo Awal Simpanan') {
                $this->assertNull($kas, "saldo awal simpanan {$t->trx_no} tidak boleh menyentuh kas");
            } else {
                $this->assertEquals($t->direction === 'in' ? $t->amount : -$t->amount, $kas->debit - $kas->credit, "efek kas {$t->trx_no}");
            }

            if (in_array($t->category, ['Simpanan', 'Saldo Awal Simpanan'], true)) {
                $this->assertNotNull($t->saving_id, "{$t->trx_no} belum tertaut ke rekening");
            }
        }

        $cash = Transaction::cashOnly();
        $net = (clone $cash)->where('direction', 'in')->sum('amount') - (clone $cash)->where('direction', 'out')->sum('amount');
        $this->assertEquals($net, JournalLine::cashBalance());
    }

    public function test_demo_books_balance_and_reconcile_with_savings_and_loans(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->actingAs(User::create(['role' => 'admin', 'name' => 'Admin', 'email' => 'a@x.test', 'password' => 'x']));

        $sheet = Reports::balanceSheet(now()->toDateString());
        $this->assertTrue($sheet['balanced'], 'Neraca demo harus seimbang');
        $this->assertGreaterThan(0, $sheet['totalEquity']);                                  // ekuitas wajar (bukan negatif)

        $res = $this->get('/laporan/neraca')->assertOk()->assertSee('Semua cocok');
        foreach ($res->viewData('reconciliation') as $row) {
            $this->assertEqualsWithDelta(0, $row['diff'], 0.01, "{$row['name']}: jurnal berbeda dengan data rekening");
        }

        // Rekening koran tiap rekening demo: saldo akhir = saldo rekening
        foreach (Saving::all() as $saving) {
            $st = $this->get("/simpanan/rekening-koran?rekening={$saving->id}")->viewData('statement');
            $this->assertEqualsWithDelta((float) $saving->balance, $st['closing'], 0.01, "rekening {$saving->account_no}");
        }
    }
}
