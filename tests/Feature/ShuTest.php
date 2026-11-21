<?php

namespace Tests\Feature;

use App\Models\JournalEntry;
use App\Models\Member;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShuTest extends TestCase
{
    use RefreshDatabase;

    private Member $a;
    private Member $b;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::create(['role' => 'admin', 'name' => 'Admin', 'email' => 'a@x.test', 'password' => 'x']));
        $this->a = Member::create(['member_no' => 'AG000001', 'name' => 'Ani', 'branch' => 'Pusat', 'joined_at' => '2020-01-01', 'status' => 'Aktif']);
        $this->b = Member::create(['member_no' => 'AG000002', 'name' => 'Budi', 'branch' => 'Pusat', 'joined_at' => '2020-01-01', 'status' => 'Aktif']);
        // Simpanan dibaca dari jurnal (kredit akun simpanan), jadi setoran dicatat sebagai jurnal.
        $this->journal('S1', [['10101', 3000000, 0], ['31102', 0, 3000000]], $this->a);
        $this->journal('S2', [['10101', 1000000, 0], ['31102', 0, 1000000]], $this->b);
    }

    // Jurnal dengan referensi transaksi (agar bunga bisa ditelusuri ke anggota) atau tanpa anggota.
    private function journal(string $no, array $lines, ?Member $member = null, string $date = null): void
    {
        $date ??= now()->toDateString();
        if ($member) {
            Transaction::create(['trx_no' => "TRX-$no", 'member_id' => $member->id, 'category' => 'Angsuran', 'direction' => 'in', 'amount' => 1, 'description' => 'x', 'trx_date' => $date, 'status' => 'Posted']);
        }
        $j = JournalEntry::create(['journal_no' => "JU-$no", 'journal_date' => $date, 'reference' => $member ? "TRX-$no" : null, 'description' => 'x', 'source' => 'AUTO JOURNAL', 'status' => 'Posted']);
        foreach ($lines as [$code, $debit, $credit]) {
            $j->lines()->create(['account_code' => $code, 'account_name' => 'x', 'debit' => $debit, 'credit' => $credit]);
        }
    }

    private function dataset(): void
    {
        $this->journal('A', [['10101', 1000000, 0], ['11301', 0, 600000], ['41101', 0, 400000]], $this->a);   // Ani bayar bunga 400.000
        $this->journal('B', [['10101', 500000, 0], ['11301', 0, 400000], ['41101', 0, 100000]], $this->b);    // Budi bayar bunga 100.000
        $this->journal('C', [['10101', 600000, 0], ['41201', 0, 600000]]);                                    // pendapatan lain 600.000
        $this->journal('D', [['51101', 300000, 0], ['10101', 0, 300000]]);                                    // beban 300.000
    }

    public function test_shu_is_income_minus_expense_and_split_by_parameters_and_member_contribution(): void
    {
        $this->dataset();
        $res = $this->get('/shu')->assertOk();

        $this->assertEquals(1100000, $res->viewData('income'));
        $this->assertEquals(300000, $res->viewData('expense'));
        $this->assertEquals(800000, $res->viewData('shu'));
        $this->assertEquals(
            ['cadangan' => 200000, 'jasa_modal' => 120000, 'jasa_usaha' => 120000, 'pengurus' => 160000, 'pengelola' => 80000, 'pendidikan' => 40000, 'sarana' => 60000, 'sosial' => 20000],
            $res->viewData('pool')->all()
        );
        $this->assertEquals(800000, $res->viewData('pool')->sum());   // 25 + 15 + 15 + 20 + 10 + 5 + 7,5 + 2,5 = 100%

        $rows = $res->viewData('members')->keyBy(fn ($r) => $r['member']->name);
        $this->assertEquals([90000, 96000, 186000], [$rows['Ani']['capital'], $rows['Ani']['business'], $rows['Ani']['total']]);    // 75% simpanan, 80% bunga
        $this->assertEquals([30000, 24000, 54000], [$rows['Budi']['capital'], $rows['Budi']['business'], $rows['Budi']['total']]);   // 25% simpanan, 20% bunga
        $this->assertEquals(240000, $res->viewData('distributed')['capital'] + $res->viewData('distributed')['business']);              // jasa modal + jasa usaha dibagikan penuh
        $this->assertSame('Ani', $res->viewData('members')->first()['member']->name);                                                    // urut dari terbesar
    }

    public function test_parameters_are_editable_but_must_total_100_percent(): void
    {
        $this->dataset();

        // Persentase boleh desimal: 40 + 30 + 10 + 5 + 5 + 5 + 2,5 + 2,5 = 100.
        $res = $this->get('/shu?cadangan=40&jasa_modal=30&jasa_usaha=10&pengurus=5&pengelola=5&pendidikan=5&sarana=2.5&sosial=2.5')->assertOk();
        $this->assertEquals(320000, $res->viewData('pool')['cadangan']);
        $this->assertEquals(240000, $res->viewData('pool')['jasa_modal']);
        $this->assertEquals(20000, $res->viewData('pool')['sarana']);

        $this->get('/shu?cadangan=50')->assertSessionHasErrors('persen');  // 50 + 75 lainnya = 125%
        $this->get('/shu?sarana=7.4')->assertSessionHasErrors('persen');   // 99,9%: desimal ikut dihitung
        $this->get('/shu?cadangan=101')->assertSessionHasErrors('cadangan');
        $this->get('/shu?cadangan=abc')->assertSessionHasErrors('cadangan');
        $this->get('/shu?tahun=abc')->assertSessionHasErrors('tahun');
    }

    public function test_capital_share_uses_the_savings_balance_at_the_end_of_the_chosen_year(): void
    {
        $last = now()->subYear()->year;
        $this->journal('S0', [['10101', 1000000, 0], ['31102', 0, 1000000]], $this->a, "$last-03-01");           // Ani setor tahun lalu
        $this->journal('S3', [['31102', 200000, 0], ['10101', 0, 200000]], $this->a, "$last-11-20");             // lalu tarik sebagian
        $this->journal('S4', [['10101', 500000, 0], ['31102', 0, 500000]], $this->b, "$last-12-31");             // Budi setor tepat di akhir tahun
        $this->journal('X', [['10101', 300000, 0], ['41101', 0, 300000]], $this->a, "$last-06-01");              // ada SHU tahun lalu

        $past = $this->get("/shu?tahun=$last")->assertOk();
        $this->assertSame("$last-12-31", $past->viewData('asOf'));
        $rows = $past->viewData('members')->keyBy(fn ($r) => $r['member']->name);
        $this->assertEquals(800000, $rows['Ani']['savings']);   // 1.000.000 - 200.000; setoran 3.000.000 tahun ini belum ada
        $this->assertEquals(500000, $rows['Budi']['savings']);  // setoran tepat 31 Desember ikut dihitung
        $this->assertEquals(1300000, $past->viewData('totalSavings'));

        $now = $this->get('/shu')->assertOk();
        $this->assertSame(now()->toDateString(), $now->viewData('asOf'));   // tahun berjalan memakai saldo hari ini
        $this->assertEquals(5300000, $now->viewData('totalSavings'));       // 3.000.000 + 1.000.000 tahun ini + 800.000 + 500.000 tahun lalu
    }

    public function test_no_shu_when_expenses_exceed_income_and_other_years_are_isolated(): void
    {
        $this->journal('E', [['10101', 100000, 0], ['41201', 0, 100000]]);
        $this->journal('F', [['51101', 500000, 0], ['10101', 0, 500000]]);
        $res = $this->get('/shu')->assertOk();
        $this->assertEquals(-400000, $res->viewData('shu'));
        $this->assertEquals(0, $res->viewData('pool')->sum());
        $this->assertEquals(0, $res->viewData('members')->sum('total'));

        $last = now()->subYear()->year;
        $this->journal('G', [['10101', 900000, 0], ['41201', 0, 900000]], null, "$last-06-15");
        $this->assertEquals(900000, $this->get("/shu?tahun=$last")->viewData('income'));      // tahun lalu terpisah
        $this->assertEquals(100000, $this->get('/shu')->viewData('income'));
    }

    public function test_page_shows_no_placeholder_numbers_and_exports_csv(): void
    {
        $this->dataset();

        $this->get('/shu')->assertOk()->assertSee('Simulasi SHU '.now()->year)->assertDontSee('418.200.000')->assertDontSee('Contoh UI')->assertDontSee('68%');
        $csv = $this->get('/shu?export=1')->assertOk()->streamedContent();
        $this->assertStringContainsString('"No Anggota";Nama;Simpanan', $csv);
        $this->assertStringContainsString('AG000001;Ani', $csv);
    }

    public function test_empty_database_renders(): void
    {
        Member::query()->delete();
        $this->get('/shu')->assertOk()->assertSee('Belum ada anggota');
    }
}
