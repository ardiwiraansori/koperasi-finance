<?php

namespace Tests\Feature;

use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Member;
use App\Models\Saving;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpeningBalanceTest extends TestCase
{
    use RefreshDatabase;

    private Member $member;
    /** @var array<string, Saving> */
    private array $acc = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::create(['role' => 'admin', 'name' => 'Admin', 'email' => 'a@x.test', 'password' => 'x']));
        $this->member = Member::create(['member_no' => 'AG000001', 'name' => 'Budi', 'branch' => 'Pusat', 'joined_at' => '2020-01-01', 'status' => 'Aktif']);
        foreach (['Pokok', 'Wajib', 'Sukarela'] as $i => $type) {
            $this->acc[$type] = $this->member->savings()->create(['account_no' => 'SAV-0001-'.($i + 1), 'type' => $type]);
        }
        $open = JournalEntry::create(['journal_no' => 'JU-OPEN', 'journal_date' => '2020-01-01', 'description' => 'Saldo awal kas', 'source' => 'AUTO JOURNAL', 'status' => 'Posted']);
        $open->lines()->create(['account_code' => '10101', 'account_name' => 'Kas Teller', 'debit' => 10000000, 'credit' => 0]);
        $open->lines()->create(['account_code' => '39101', 'account_name' => 'Ekuitas Saldo Awal', 'debit' => 0, 'credit' => 10000000]);
    }

    private function opening(array $saldo, ?string $date = null)
    {
        return $this->post('/simpanan/saldo-awal', ['member_id' => $this->member->id, 'tanggal' => $date ?? now()->subDays(30)->toDateString(), 'saldo' => $saldo]);
    }

    public function test_opening_balance_fills_savings_posts_equity_journal_and_leaves_cash_untouched(): void
    {
        $this->opening(['Pokok' => '500.000', 'Wajib' => '1.200.000', 'Sukarela' => ''])->assertSessionHasNoErrors();

        $this->assertEquals(500000, $this->acc['Pokok']->fresh()->balance);
        $this->assertEquals(1200000, $this->acc['Wajib']->fresh()->balance);
        $this->assertEquals(0, $this->acc['Sukarela']->fresh()->balance);       // dikosongkan = dilewati
        $this->assertSame(10000000.0, JournalLine::cashBalance());               // kas TIDAK berubah

        $wajib = Transaction::where('saving_id', $this->acc['Wajib']->id)->firstOrFail();
        $this->assertSame('Saldo Awal Simpanan', $wajib->category);
        $this->assertSame(now()->subDays(30)->toDateString(), $wajib->trx_date->toDateString());

        $lines = JournalEntry::with('lines')->where('reference', $wajib->trx_no)->firstOrFail()->lines
            ->mapWithKeys(fn ($l) => [$l->account_code => [(float) $l->debit, (float) $l->credit]])->all();
        $this->assertSame(['39101' => [1200000.0, 0.0], '31102' => [0.0, 1200000.0]], $lines);   // D Ekuitas Saldo Awal / K Simpanan Wajib
    }

    public function test_opening_balance_rules(): void
    {
        $this->opening([])->assertSessionHasErrors('saldo');                                   // minimal satu saldo
        $this->opening(['Wajib' => '1.000'], now()->addDay()->toDateString())->assertSessionHasErrors('tanggal');
        $this->assertEquals(0, $this->acc['Wajib']->fresh()->balance);

        $this->opening(['Wajib' => '1.000.000'])->assertSessionHasNoErrors();
        $this->opening(['Wajib' => '2.000.000'])->assertSessionHasErrors('saldo.Wajib');       // hanya sekali per rekening
        $this->assertEquals(1000000, $this->acc['Wajib']->fresh()->balance);

        $this->post('/simpanan/setoran', ['saving_id' => $this->acc['Sukarela']->id, 'amount' => '50.000'])->assertSessionHasNoErrors();
        $this->opening(['Sukarela' => '3.000.000'])->assertSessionHasErrors('saldo.Sukarela'); // rekening sudah dipakai
        $this->assertEquals(50000, $this->acc['Sukarela']->fresh()->balance);

        // gagal di tengah = tidak ada yang tersimpan (Pokok valid, Sukarela ditolak)
        $this->opening(['Pokok' => '100.000', 'Sukarela' => '1.000'])->assertSessionHasErrors('saldo.Sukarela');
        $this->assertEquals(0, $this->acc['Pokok']->fresh()->balance);
    }

    public function test_statement_folds_opening_balance_into_saldo_awal_row(): void
    {
        $this->opening(['Sukarela' => '1.000.000']);
        $this->post('/simpanan/setoran', ['saving_id' => $this->acc['Sukarela']->id, 'amount' => '200.000']);
        $this->post('/simpanan/penarikan', ['saving_id' => $this->acc['Sukarela']->id, 'amount' => '100.000']);
        $id = $this->acc['Sukarela']->id;

        $st = $this->get("/simpanan/rekening-koran?rekening=$id")->assertOk()->viewData('statement');
        $this->assertSame([1000000.0, 200000.0, 100000.0, 1100000.0, 2], [$st['opening'], $st['in'], $st['out'], $st['closing'], $st['rows']->count()]);
        $this->assertEquals($this->acc['Sukarela']->fresh()->balance, $st['closing']);

        $later = $this->get("/simpanan/rekening-koran?rekening=$id&dari=".now()->addDay()->toDateString())->viewData('statement');
        $this->assertSame([1100000.0, 1100000.0, 0], [$later['opening'], $later['closing'], $later['rows']->count()]);
    }

    public function test_opening_balance_is_not_cash_flow_or_income(): void
    {
        $this->opening(['Wajib' => '1.200.000'], now()->toDateString())->assertSessionHasNoErrors();

        $dash = $this->get('/dashboard')->assertOk();
        $this->assertSame(0.0, (float) $dash->viewData('stats')['income']);
        $this->assertSame(0.0, (float) $dash->viewData('stats')['savingsNet']);
        $this->assertCount(0, $dash->viewData('transactions'));                 // bukan aktivitas kasir
        $this->get('/kasir')->assertOk()->assertDontSee('Saldo awal simpanan');
        $this->assertFalse($dash->viewData('chart')['hasData']);
    }

    public function test_pages_render(): void
    {
        $this->get('/simpanan/saldo-awal')->assertOk()->assertSee('Saldo Awal Simpanan');
        $this->get('/simpanan/saldo-awal?anggota='.$this->member->id)->assertOk()->assertSee('SAV-0001-1')->assertSee('saldo[Wajib]', false);

        $this->opening(['Wajib' => '1.000.000']);
        $this->get('/simpanan/saldo-awal?anggota='.$this->member->id)->assertSee('saldo awal tidak bisa diisi');   // Wajib terkunci
    }
}
