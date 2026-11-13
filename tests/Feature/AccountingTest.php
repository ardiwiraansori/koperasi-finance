<?php

namespace Tests\Feature;

use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Saving;
use App\Models\TransactionType;
use App\Models\User;
use App\Support\Coa;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \App\Models\Setting::put('has_accountant', '1'); // mode dengan akuntan: jurnal manual dan pemetaan akun tersedia
        $this->actingAs(User::create(['role' => 'admin', 'name' => 'Admin', 'email' => 'a@x.test', 'password' => 'x']));
    }

    private function manual(array $override = [])
    {
        return $this->post('/akuntansi/jurnal-manual', $override + [
            'tanggal' => now()->toDateString(), 'keterangan' => 'Penyusutan inventaris',
            'lines' => [['account' => '51501', 'debit' => '100.000', 'credit' => ''], ['account' => '12301', 'debit' => '', 'credit' => '100.000']],
        ]);
    }

    public function test_chart_of_accounts_is_the_single_source_of_account_names(): void
    {
        $used = collect(Saving::ACCOUNTS)->values()->push(Saving::OPENING, ['10101', 'Kas Teller'], ['11301', 'Piutang Pinjaman'], ['41101', 'Pendapatan Bunga'])
            ->merge(TransactionType::all()->map(fn ($t) => [$t->account_code, Coa::name($t->account_code)]));

        foreach ($used as [$code, $name]) {
            $this->assertArrayHasKey($code, Coa::ACCOUNTS, "akun $code belum ada di bagan akun");
            $this->assertSame($name, Coa::name($code), "nama akun $code berbeda dengan bagan akun");
        }
        foreach (Coa::SYSTEM as $code) {
            $this->assertArrayHasKey($code, Coa::ACCOUNTS);
            $this->assertArrayNotHasKey($code, Coa::manual());
        }
    }

    public function test_journal_list_filters_summary_and_pagination(): void
    {
        $this->seed(DatabaseSeeder::class);
        $total = JournalEntry::count();

        $res = $this->get('/akuntansi')->assertOk()->assertSee('Jurnal Umum');
        $this->assertSame($total, $res->viewData('count'));
        $this->assertEquals($res->viewData('debit'), $res->viewData('credit'));       // seluruh jurnal seimbang

        $this->assertLessThan($total, $this->get('/akuntansi?q=angsuran')->viewData('count'));
        $this->assertGreaterThan(0, $this->get('/akuntansi?q=angsuran')->viewData('count'));
        $this->assertSame(0, $this->get('/akuntansi?sumber=MANUAL')->viewData('count'));
        $this->assertSame($total, $this->get('/akuntansi?sumber=AUTO JOURNAL')->viewData('count'));
        $this->assertSame(0, $this->get('/akuntansi?dari='.now()->addYear()->toDateString())->viewData('count'));
        $this->get('/akuntansi?dari=2026-12-31&sampai=2026-01-01')->assertSessionHasErrors('sampai');
    }

    public function test_export_csv_neutralizes_formulas(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->manual(['keterangan' => '=HYPERLINK("http://x")'])->assertSessionHasNoErrors();

        $csv = $this->get('/akuntansi/export')->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8')->streamedContent();
        $this->assertStringContainsString('Tanggal;"No Jurnal";Referensi', $csv);
        $this->assertStringContainsString('JU-', $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringNotContainsString(';=HYPERLINK', $csv);
        $this->assertSame(JournalLine::count(), substr_count($csv, "\n") - 1);          // satu baris per baris jurnal + header
    }

    public function test_ledger_running_balance_uses_the_normal_side_of_the_account(): void
    {
        $this->seed(DatabaseSeeder::class);

        $cash = $this->get('/akuntansi/buku-besar?akun=10101')->assertOk()->viewData('ledger');
        $this->assertEqualsWithDelta(JournalLine::cashBalance(), $cash['closing'], 0.01);   // buku besar kas = saldo kas

        $income = $this->get('/akuntansi/buku-besar?akun=41101')->viewData('ledger');        // pendapatan: saldo normal kredit
        $this->assertGreaterThan(0, $income['closing']);
        $this->assertEqualsWithDelta($income['credit'] - $income['debit'], $income['closing'], 0.01);

        $latest = JournalEntry::max('journal_date');
        $after = $this->get('/akuntansi/buku-besar?akun=10101&dari='.substr($latest, 0, 10).'&sampai='.substr($latest, 0, 10))->viewData('ledger');
        $this->assertEqualsWithDelta($after['closing'], $after['opening'] + $after['debit'] - $after['credit'], 0.01);
        $this->get('/akuntansi/buku-besar')->assertOk();                                      // tanpa akun: hanya form
    }

    public function test_trial_balance_is_balanced_and_respects_the_cutoff_date(): void
    {
        $this->seed(DatabaseSeeder::class);

        $res = $this->get('/akuntansi/neraca-saldo')->assertOk()->assertSee('Seimbang');
        $t = $res->viewData('totals');
        $this->assertEqualsWithDelta($t['debit'], $t['credit'], 0.01);
        $this->assertEqualsWithDelta($t['balD'], $t['balK'], 0.01);
        $this->assertSame(JournalLine::cashBalance(), (float) $res->viewData('rows')->firstWhere('code', '10101')['balD']);

        $this->assertCount(0, $this->get('/akuntansi/neraca-saldo?sampai=2000-01-01')->viewData('rows'));
        $csv = $this->get('/akuntansi/neraca-saldo?export=1')->assertOk()->streamedContent();
        $this->assertStringContainsString('10101;"Kas Teller"', $csv);
    }

    public function test_manual_journal_is_posted_balanced_and_does_not_touch_cash(): void
    {
        $this->seed(DatabaseSeeder::class);
        $cash = JournalLine::cashBalance();

        $this->manual()->assertSessionHasNoErrors();
        $j = JournalEntry::with('lines')->where('source', 'MANUAL')->firstOrFail();
        $this->assertStringStartsWith('JM-', $j->journal_no);
        $this->assertSame('Posted', $j->status);
        $this->assertSame(['51501' => [100000.0, 0.0], '12301' => [0.0, 100000.0]], $j->lines->mapWithKeys(fn ($l) => [$l->account_code => [(float) $l->debit, (float) $l->credit]])->all());
        $this->assertSame($cash, JournalLine::cashBalance());
        $this->get('/akuntansi?sumber=MANUAL')->assertSee($j->journal_no);
    }

    public function test_manual_journal_validation(): void
    {
        $before = JournalEntry::count();
        $line = fn (string $acc, string $d = '', string $k = '') => ['account' => $acc, 'debit' => $d, 'credit' => $k];

        $this->manual(['lines' => [$line('51501', '100.000'), $line('12301', '', '90.000')]])->assertSessionHasErrors('lines');            // tidak seimbang
        $this->manual(['lines' => [$line('51501', '100.000'), $line('10101', '', '100.000')]])->assertSessionHasErrors('lines.1.account');  // kas dikunci
        $this->manual(['lines' => [$line('51501', '100.000'), $line('11301', '', '100.000')]])->assertSessionHasErrors('lines.1.account');  // piutang dikunci
        $this->manual(['lines' => [$line('51501', '100.000'), $line('31102', '', '100.000')]])->assertSessionHasErrors('lines.1.account');  // simpanan dikunci
        $this->manual(['lines' => [$line('51501', '100.000')]])->assertSessionHasErrors('lines');                                            // satu baris
        $this->manual(['lines' => [$line('51501', '100.000', '100.000'), $line('12301', '', '100.000')]])->assertSessionHasErrors('lines'); // debit dan kredit sekaligus
        $this->manual(['lines' => [$line('51501', '0'), $line('12301', '', '0')]])->assertSessionHasErrors();                                // nominal nol
        $this->manual(['tanggal' => now()->addDay()->toDateString()])->assertSessionHasErrors('tanggal');
        $this->manual(['keterangan' => ''])->assertSessionHasErrors('keterangan');
        $this->assertSame($before, JournalEntry::count());
    }

    public function test_pages_render_with_navigation_tabs(): void
    {
        foreach (['/akuntansi', '/akuntansi/buku-besar', '/akuntansi/neraca-saldo', '/akuntansi/jurnal-manual'] as $url) {
            $this->get($url)->assertOk()->assertSee('Neraca Saldo')->assertSee('Jurnal Manual');
        }
        $this->get('/akuntansi/jurnal-manual')->assertSee('data-journal-form', false)->assertDontSee('10101 — Kas Teller');
    }
}
