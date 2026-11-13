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
        $this->a->savings()->create(['account_no' => 'SAV-1', 'type' => 'Wajib', 'balance' => 3000000]);
        $this->b->savings()->create(['account_no' => 'SAV-2', 'type' => 'Wajib', 'balance' => 1000000]);
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
            ['cadangan' => 200000, 'jasa_modal' => 160000, 'jasa_usaha' => 240000, 'pengurus' => 80000, 'pendidikan' => 120000],
            $res->viewData('pool')->all()
        );

        $rows = $res->viewData('members')->keyBy(fn ($r) => $r['member']->name);
        $this->assertEquals([120000, 192000, 312000], [$rows['Ani']['capital'], $rows['Ani']['business'], $rows['Ani']['total']]);   // 75% simpanan, 80% bunga
        $this->assertEquals([40000, 48000, 88000], [$rows['Budi']['capital'], $rows['Budi']['business'], $rows['Budi']['total']]);   // 25% simpanan, 20% bunga
        $this->assertEquals(400000, $res->viewData('distributed')['capital'] + $res->viewData('distributed')['business']);              // jasa modal + jasa usaha dibagikan penuh
        $this->assertSame('Ani', $res->viewData('members')->first()['member']->name);                                                    // urut dari terbesar
    }

    public function test_parameters_are_editable_but_must_total_100_percent(): void
    {
        $this->dataset();

        $res = $this->get('/shu?cadangan=40&jasa_modal=40&jasa_usaha=10&pengurus=5&pendidikan=5')->assertOk();
        $this->assertEquals(320000, $res->viewData('pool')['cadangan']);
        $this->assertEquals(320000, $res->viewData('pool')['jasa_modal']);

        $this->get('/shu?cadangan=50&jasa_modal=20&jasa_usaha=30&pengurus=10&pendidikan=15')->assertSessionHasErrors('persen');  // 125%
        $this->get('/shu?cadangan=101')->assertSessionHasErrors('cadangan');
        $this->get('/shu?tahun=abc')->assertSessionHasErrors('tahun');
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
