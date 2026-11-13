<?php

namespace Tests\Feature;

use App\Models\JournalLine;
use App\Models\Loan;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::create(['role' => 'admin', 'name' => 'Siti Admin', 'email' => 'a@x.test', 'password' => 'x']));
    }

    public function test_dashboard_numbers_come_from_data_not_from_placeholders(): void
    {
        $this->seed(DatabaseSeeder::class);
        $res = $this->get('/dashboard')->assertOk();
        $stats = $res->viewData('stats');
        $quality = $res->viewData('quality');

        $this->assertSame(JournalLine::cashBalance(), $stats['cash']);
        $this->assertSame(3, $stats['loanCount']);                                   // 3 pinjaman berjalan pada data demo
        $this->assertSame(1, $stats['arrearsCount']);                                // satu pinjaman lewat jatuh tempo
        $this->assertEquals(25000000, $stats['arrears']);
        $this->assertEquals(8250000 + 25000000 + 10980000, $stats['loans']);
        $this->assertSame(1, $res->viewData('queue')['pending']);                    // satu pengajuan menunggu approval

        // 28 hari lewat tempo: masuk bucket 1-90 hari, bukan NPL
        $this->assertEquals(25000000, $quality['watch']);
        $this->assertEquals(0, $quality['npl']);
        $this->assertEqualsWithDelta(100 - 25000000 / 44230000 * 100, $quality['score'], 1); // skor = 100 - persen pinjaman menunggak

        $res->assertSee('Siti Admin')->assertDontSee('NPL Demo')->assertDontSee('sengaja dibuat')->assertDontSee('4,8%')->assertDontSee('dari bulan lalu');
    }

    public function test_cash_flow_chart_aggregates_by_month_and_ignores_opening_balance(): void
    {
        $this->seed(DatabaseSeeder::class);
        Transaction::query()->delete();
        $mk = fn (string $dir, float $amt, $date, string $cat = 'Penerimaan') => Transaction::create([
            'trx_no' => 'T'.uniqid(), 'category' => $cat, 'direction' => $dir, 'amount' => $amt, 'description' => 'x', 'trx_date' => $date, 'status' => 'Posted',
        ]);
        $mk('in', 1000000, now());
        $mk('in', 500000, now()->subMonth());
        $mk('out', 300000, now());
        $mk('in', 99000000, now(), 'Saldo Awal');                                     // tidak boleh masuk grafik maupun "kas masuk"

        $res = $this->get('/dashboard')->assertOk();
        $chart = $res->viewData('chart');

        $this->assertTrue($chart['hasData']);
        $this->assertCount(8, $chart['labels']);
        $this->assertEquals(1000000, $chart['max']);                                  // skala grafik = nilai bulanan terbesar
        $this->assertEquals(1000000, $res->viewData('stats')['income']);
        $this->assertStringStartsWith('M44', $chart['incomeLine']);
        $this->assertStringContainsString('L740', $chart['incomeLine']);
    }

    public function test_empty_database_renders_without_errors(): void
    {
        $res = $this->get('/dashboard')->assertOk()->assertSee('Belum ada transaksi');
        $this->assertSame(100, $res->viewData('quality')['score']);
        $this->assertSame(0, Loan::count());
    }
}
