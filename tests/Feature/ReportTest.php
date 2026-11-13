<?php

namespace Tests\Feature;

use App\Models\JournalEntry;
use App\Models\Loan;
use App\Models\Member;
use App\Models\User;
use App\Services\Reports;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::create(['role' => 'admin', 'name' => 'Admin', 'email' => 'a@x.test', 'password' => 'x']));
    }

    private function journal(string $no, string $date, array $lines): void
    {
        $j = JournalEntry::create(['journal_no' => "JU-$no", 'journal_date' => $date, 'description' => "Jurnal $no", 'source' => 'AUTO JOURNAL', 'status' => 'Posted']);
        foreach ($lines as [$code, $debit, $credit]) {
            $j->lines()->create(['account_code' => $code, 'account_name' => 'x', 'debit' => $debit, 'credit' => $credit]);
        }
    }

    // Tahun lalu: modal awal 100jt, pendapatan bunga 10jt, beban 4jt. Tahun ini: pendapatan 6jt, beban 2jt, pinjaman cair 20jt, simpanan 30jt.
    private function dataset(): int
    {
        $y = now()->year;
        $last = $y - 1;
        $this->journal('A', "$last-01-05", [['10101', 100000000, 0], ['39101', 0, 100000000]]);        // saldo awal kas
        $this->journal('B', "$last-06-10", [['10101', 10000000, 0], ['41101', 0, 10000000]]);          // pendapatan tahun lalu
        $this->journal('C', "$last-09-10", [['51101', 4000000, 0], ['10101', 0, 4000000]]);            // beban tahun lalu
        $this->journal('D', "$y-01-10", [['10101', 6000000, 0], ['41201', 0, 6000000]]);               // pendapatan tahun ini
        $this->journal('E', "$y-01-20", [['51201', 2000000, 0], ['10101', 0, 2000000]]);               // beban tahun ini
        $this->journal('F', "$y-02-01", [['11301', 20000000, 0], ['10101', 0, 20000000]]);             // pencairan pinjaman
        $this->journal('G', "$y-02-02", [['10101', 30000000, 0], ['31102', 0, 30000000]]);             // simpanan wajib masuk
        $this->journal('H', "$y-03-01", [['12101', 1000000, 0], ['21101', 0, 1000000]]);               // perlengkapan dibeli kredit
        return $y;
    }

    public function test_income_statement_for_a_period(): void
    {
        $y = $this->dataset();
        $res = $this->get("/laporan/laba-rugi?dari=$y-01-01&sampai=$y-12-31")->assertOk();
        $now = $res->viewData('now');

        $this->assertEquals(6000000, $now['totalIncome']);
        $this->assertEquals(2000000, $now['totalExpense']);
        $this->assertEquals(4000000, $now['shu']);
        $this->assertSame(['41201'], $now['income']->pluck('code')->all());
        $this->assertSame(['51201'], $now['expense']->pluck('code')->all());
        $res->assertSee('SISA HASIL USAHA')->assertSee('Surplus')->assertSee('66,7%');        // margin 4jt / 6jt

        $last = $y - 1;
        $other = $this->get("/laporan/laba-rugi?dari=$last-01-01&sampai=$last-12-31")->viewData('now');
        $this->assertEquals([10000000, 4000000, 6000000], [$other['totalIncome'], $other['totalExpense'], $other['shu']]);
        $this->assertSame(0, Reports::incomeStatement("$y-04-01", "$y-04-30")['income']->count());   // periode tanpa jurnal
    }

    public function test_income_statement_comparison_and_deficit(): void
    {
        $y = $this->dataset();
        $res = $this->get("/laporan/laba-rugi?dari=$y-01-01&sampai=$y-12-31&bandingkan=1")->assertOk();

        $this->assertEquals("$y-01-01", $res->viewData('from'));
        $this->assertEquals(($y - 1).'-12-31', $res->viewData('prevTo'));                       // pembanding tepat sebelum periode
        $this->assertEquals(10000000, $res->viewData('prev')['totalIncome']);                   // tahun lalu
        $res->assertSee('Perubahan');

        $this->journal('X', "$y-05-05", [['59101', 50000000, 0], ['10101', 0, 50000000]]);
        $this->get("/laporan/laba-rugi?dari=$y-01-01&sampai=$y-12-31")->assertSee('Defisit')->assertSee('(Rp');   // rugi tampil di dalam kurung
    }

    public function test_balance_sheet_balances_and_splits_current_and_prior_year_shu(): void
    {
        $y = $this->dataset();
        $res = $this->get('/laporan/neraca?tanggal='.$y.'-12-31')->assertOk();
        $b = $res->viewData('now');

        // Aset: kas 100+10-4+6-2-20+30 = 120jt, piutang 20jt, perlengkapan 1jt = 141jt
        $this->assertEquals(120000000, $b['assets']->firstWhere('code', '10101')['amount']);
        $this->assertEquals(141000000, $b['totalAssets']);
        $this->assertEquals(1000000, $b['totalLiabilities']);                                    // utang beban
        $this->assertEquals(4000000, $b['shuCurrent']);                                          // 6jt - 2jt
        $this->assertEquals(6000000, $b['shuPrior']);                                            // 10jt - 4jt tahun lalu
        $this->assertEquals(100000000 + 30000000 + 4000000 + 6000000, $b['totalEquity']);        // 39101 + 31102 + SHU
        $this->assertTrue($b['balanced']);
        $res->assertSee('Aset = Kewajiban + Ekuitas')->assertSee('TOTAL KEWAJIBAN DAN EKUITAS');
    }

    public function test_balance_sheet_respects_the_date_and_compares_with_last_year_end(): void
    {
        $y = $this->dataset();
        $last = $y - 1;

        $early = Reports::balanceSheet("$last-03-01");                                           // hanya saldo awal kas
        $this->assertEquals(100000000, $early['totalAssets']);
        $this->assertEquals(0.0, $early['shuCurrent']);
        $this->assertTrue($early['balanced']);

        $res = $this->get("/laporan/neraca?tanggal=$y-12-31&bandingkan=1")->assertOk();
        $this->assertEquals(106000000, $res->viewData('prev')['totalAssets']);                    // 31 Des tahun lalu: 100+10-4
        $this->assertEquals($last.'-12-31', $res->viewData('prevDate'));
        $res->assertSee('Bandingkan dengan akhir tahun lalu');
    }

    public function test_contra_asset_reduces_total_assets(): void
    {
        $y = $this->dataset();
        $this->journal('Z', "$y-04-01", [['51501', 300000, 0], ['12301', 0, 300000]]);            // penyusutan: akumulasi (kredit) pengurang aset
        $b = Reports::balanceSheet("$y-12-31");

        $this->assertEquals(-300000, $b['assets']->firstWhere('code', '12301')['amount']);
        $this->assertEquals(141000000 - 300000, $b['totalAssets']);
        $this->assertTrue($b['balanced']);
    }

    public function test_reconciliation_flags_differences_with_savings_and_loans(): void
    {
        $y = $this->dataset();
        $m = Member::create(['member_no' => 'AG000001', 'name' => 'Budi', 'branch' => 'Pusat', 'joined_at' => '2020-01-01', 'status' => 'Aktif']);
        $m->savings()->create(['account_no' => 'SAV-1', 'type' => 'Wajib', 'balance' => 30000000]);                                  // sama dengan jurnal 31102
        Loan::create(['member_id' => $m->id, 'loan_no' => 'PIN-1', 'product' => 'Pinjaman Reguler', 'principal' => 20000000, 'interest_rate' => 12, 'term_months' => 12,
            'installment' => 1, 'outstanding' => 18000000, 'status' => 'Aktif', 'applied_at' => today(), 'next_due_at' => today()->addMonth()]);   // jurnal 11301 = 20jt

        $rec = collect($this->get('/laporan/neraca')->assertOk()->viewData('reconciliation'))->keyBy('code');
        $this->assertEquals(0, $rec['31102']['diff']);
        $this->assertEquals(2000000, $rec['11301']['diff']);                                                                         // 20jt di jurnal vs 18jt di pinjaman
        $this->get('/laporan/neraca')->assertSee('Ada selisih')->assertSee('Periksa');

        $this->assertNull($this->get('/laporan/neraca?tanggal='.now()->subDay()->toDateString())->viewData('reconciliation'));       // hanya untuk hari ini
        $this->assertNotNull($y);
    }

    public function test_exports_and_validation_and_index(): void
    {
        $y = $this->dataset();

        $csv = $this->get("/laporan/neraca?tanggal=$y-12-31&export=1")->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8')->streamedContent();
        $this->assertStringContainsString('Kelompok;Kode;Akun', $csv);
        $this->assertStringContainsString('Aset;10101;"Kas Teller";120000000', $csv);
        $this->assertStringContainsString('"SHU Tahun Berjalan";4000000', $csv);

        $csv = $this->get("/laporan/laba-rugi?dari=$y-01-01&sampai=$y-12-31&export=1")->assertOk()->streamedContent();
        $this->assertStringContainsString('Pendapatan;41201;', $csv);
        $this->assertStringContainsString('"Sisa Hasil Usaha (SHU)";4000000', $csv);

        $this->get('/laporan/laba-rugi?dari=2026-12-31&sampai=2026-01-01')->assertSessionHasErrors('sampai');
        $this->get('/laporan/neraca?tanggal=bukan-tanggal')->assertSessionHasErrors('tanggal');
        $this->get('/laporan')->assertOk()->assertSee('Neraca')->assertSee('Laba Rugi')->assertSee('Segera')->assertSee(route('reports.balance'), false);
    }

    public function test_empty_books_render_without_errors(): void
    {
        $this->get('/laporan/neraca')->assertOk()->assertSee('Belum ada saldo');
        $this->get('/laporan/laba-rugi')->assertOk()->assertSee('Tidak ada pendapatan');
    }
}
