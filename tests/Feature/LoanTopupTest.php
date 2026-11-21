<?php

namespace Tests\Feature;

use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Loan;
use App\Models\Member;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Top-up: anggota dengan pinjaman berjalan mengajukan pinjaman baru; pinjaman lama dilunasi dari pencairan baru.
class LoanTopupTest extends TestCase
{
    use RefreshDatabase;

    private Member $member;
    private User $admin;
    private User $kasir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::create(['role' => 'admin', 'name' => 'Admin', 'email' => 'a@x.test', 'password' => 'x']);
        $this->kasir = User::create(['role' => 'kasir', 'name' => 'Kasir', 'email' => 'k@x.test', 'password' => 'x']);
        $this->member = Member::create(['member_no' => 'AG000001', 'name' => 'Budi', 'branch' => 'Pusat', 'joined_at' => '2026-01-01', 'status' => 'Aktif']);
        $this->cash(50000000);
    }

    private function cash(float $amount): void
    {
        $j = JournalEntry::create(['journal_no' => 'JU-OPEN'.uniqid(), 'journal_date' => '2026-01-01', 'description' => 'Saldo awal', 'source' => 'AUTO JOURNAL', 'status' => 'Posted']);
        $j->lines()->create(['account_code' => '10101', 'account_name' => 'Kas Teller', 'debit' => $amount, 'credit' => 0]);
        $j->lines()->create(['account_code' => '39101', 'account_name' => 'Ekuitas Saldo Awal', 'debit' => 0, 'credit' => $amount]);
        // piutang sebesar pinjaman lama dibuat di running(); kas awal ini hanya untuk pencairan
    }

    /** Pinjaman lama yang sedang berjalan (bunga 12,5%): bunga bulan ini = sisa pokok x 12,5 / 1200. */
    private function running(float $outstanding, array $attrs = []): Loan
    {
        return Loan::create($attrs + [
            'member_id' => $this->member->id, 'loan_no' => 'PIN-'.uniqid(), 'product' => Loan::PRODUCT, 'principal' => 10000000, 'interest_rate' => 12.5,
            'term_months' => 12, 'installment' => Loan::annuity(10000000, 12.5, 12), 'outstanding' => $outstanding, 'status' => 'Aktif',
            'applied_at' => today()->subMonths(6), 'disbursed_at' => today()->subMonths(6), 'next_due_at' => today()->addDays(10),
        ]);
    }

    private function apply(string $principal)
    {
        $this->actingAs($this->kasir);
        return $this->post('/pinjaman', ['member_id' => $this->member->id, 'principal' => $principal, 'term_months' => 12]);
    }

    private function approveAndDisburse(Loan $loan)
    {
        $this->actingAs($this->admin);
        $this->post("/pinjaman/{$loan->id}/setujui")->assertSessionHasNoErrors();
        $this->actingAs($this->kasir);                                  // pencairan oleh kasir
        return $this->post("/pinjaman/{$loan->id}/cairkan");
    }

    public function test_topup_settles_the_old_loan_and_only_the_difference_leaves_the_cash(): void
    {
        $old = $this->running(3000000);                                   // bunga bulan ini: 3.000.000 x 12,5 / 1200 = 31.250
        $this->apply('10.000.000')->assertSessionHasNoErrors();
        $new = Loan::where('id', '!=', $old->id)->firstOrFail();

        $this->approveAndDisburse($new)->assertSessionHasNoErrors()->assertSessionHas('success');

        $old->refresh();
        $new->refresh();
        $this->assertSame(['Lunas', 0.0, $new->id], [$old->status, (float) $old->outstanding, $old->settled_by_loan_id]);
        $this->assertNull($old->next_due_at);
        $this->assertSame(['Aktif', 10000000.0], [$new->status, (float) $new->outstanding]);   // bunga dan cicilan dari plafon penuh
        $this->assertEquals(Loan::annuity(10000000, 12.5, 12), $new->installment);
        $this->assertSame(50000000.0 - (10000000 - 3031250), JournalLine::cashBalance());      // kas hanya keluar 6.968.750

        // Pelunasan tercatat di sejarah pinjaman lama sebagai angsuran; jurnal pelunasan seimbang dan membagi pokok dan bunga.
        $payoff = Transaction::where('loan_id', $old->id)->where('category', 'Angsuran')->firstOrFail();
        $this->assertEquals(3031250, $payoff->amount);
        $lines = JournalEntry::where('reference', $payoff->trx_no)->firstOrFail()->lines->mapWithKeys(fn ($l) => [$l->account_code => [(float) $l->debit, (float) $l->credit]])->all();
        $this->assertSame(['10101' => [3031250.0, 0.0], '11301' => [0.0, 3000000.0], '41101' => [0.0, 31250.0]], $lines);
        $this->assertSame(0, JournalEntry::with('lines')->get()->filter(fn ($j) => round($j->lines->sum('debit'), 2) !== round($j->lines->sum('credit'), 2))->count());

        $this->get("/pinjaman/{$old->id}")->assertOk()->assertSee('Dilunasi lewat top-up')->assertSee($new->loan_no);
        $this->get("/pinjaman/{$new->id}")->assertOk()->assertSee('melunasi')->assertSee($old->loan_no);
    }

    public function test_overdue_loan_is_settled_the_same_way(): void
    {
        $old = $this->running(3000000, ['status' => 'Tunggakan', 'next_due_at' => today()->subDays(20)]);
        $this->apply('10.000.000')->assertSessionHasNoErrors();
        $new = Loan::where('id', '!=', $old->id)->firstOrFail();

        $this->actingAs($this->admin);
        $this->get('/approval')->assertOk()->assertSee('20 hari terlambat')->assertSee('Top-up: pelunasan pinjaman lama');
        $this->approveAndDisburse($new)->assertSessionHasNoErrors();

        $this->assertSame('Lunas', $old->fresh()->status);
        $this->assertSame('Aktif', $new->fresh()->status);
    }

    public function test_every_missed_month_of_interest_is_charged_on_an_overdue_loan(): void
    {
        // Terlambat 95 hari: jatuh tempo sekitar hari ke 0, 30, 60, dan 90 sudah lewat = 4 bulan bunga (31.250 x 4 = 125.000).
        $old = $this->running(3000000, ['status' => 'Tunggakan', 'next_due_at' => today()->subDays(95)]);
        $this->assertSame(4, $old->missedMonths());
        $this->apply('10.000.000')->assertSessionHasNoErrors();
        $new = Loan::where('id', '!=', $old->id)->firstOrFail();

        $this->actingAs($this->admin);
        $this->get('/approval')->assertOk()->assertSee('(4 bulan)');
        $this->approveAndDisburse($new)->assertSessionHasNoErrors();

        $payoff = Transaction::where('loan_id', $old->id)->where('category', 'Angsuran')->firstOrFail();
        $this->assertEquals(3125000, $payoff->amount);
        $lines = JournalEntry::where('reference', $payoff->trx_no)->firstOrFail()->lines->mapWithKeys(fn ($l) => [$l->account_code => [(float) $l->debit, (float) $l->credit]])->all();
        $this->assertSame(['10101' => [3125000.0, 0.0], '11301' => [0.0, 3000000.0], '41101' => [0.0, 125000.0]], $lines);
        $this->assertSame(50000000.0 - (10000000 - 3125000), JournalLine::cashBalance());
    }

    public function test_missed_months_counts_only_due_dates_that_have_passed(): void
    {
        $this->assertSame(1, $this->running(1000000)->missedMonths());                                                              // lancar: bulan berjalan
        $this->assertSame(1, $this->running(1000000, ['next_due_at' => today()])->missedMonths());                                    // jatuh tempo hari ini belum lewat
        $this->assertSame(1, $this->running(1000000, ['status' => 'Tunggakan', 'next_due_at' => today()->subDay()])->missedMonths());
        $this->assertSame(2, $this->running(1000000, ['status' => 'Tunggakan', 'next_due_at' => today()->subMonthNoOverflow()->subDay()])->missedMonths());
        $this->assertSame(1, $this->running(1000000, ['status' => 'Tunggakan', 'next_due_at' => today()->subMonthNoOverflow()])->missedMonths());   // bulan ini jatuh tepat hari ini
    }

    public function test_all_running_loans_of_the_member_are_settled_together(): void
    {
        $a = $this->running(2000000);                                     // total 2.020.833 (bunga 20.833)
        $b = $this->running(1000000);                                     // total 1.010.417 (bunga 10.417)
        $this->apply('5.000.000')->assertSessionHasNoErrors();
        $new = Loan::whereNotIn('id', [$a->id, $b->id])->firstOrFail();

        $this->approveAndDisburse($new)->assertSessionHasNoErrors();

        $this->assertSame(['Lunas', 'Lunas'], [$a->fresh()->status, $b->fresh()->status]);
        $this->assertSame(2, Loan::where('settled_by_loan_id', $new->id)->count());
        $this->assertSame(1, Loan::whereIn('status', Loan::RUNNING)->count());     // tinggal satu pinjaman berjalan
        $this->assertSame(50000000.0 - (5000000 - 2020833 - 1010417), JournalLine::cashBalance());
    }

    public function test_plafon_must_be_larger_than_the_payoff_at_application_and_at_disbursement(): void
    {
        $old = $this->running(3000000);                                   // kewajiban 3.031.250

        $this->apply('3.000.000')->assertSessionHasErrors('principal');   // lebih kecil
        $this->apply('3.031.250')->assertSessionHasErrors('principal');   // sama: tidak ada uang yang bisa dicairkan
        $this->assertSame(1, Loan::count());

        $this->apply('3.500.000')->assertSessionHasNoErrors();
        $new = Loan::where('id', '!=', $old->id)->firstOrFail();
        $this->actingAs($this->admin);
        $this->post("/pinjaman/{$new->id}/setujui");

        // Sejak pengajuan, pinjaman lama bertambah besar (mis. data diperbarui): kewajiban kini 4.052.083 lebih besar dari plafon.
        $old->update(['outstanding' => 4000000]);
        $this->actingAs($this->kasir);
        $this->post("/pinjaman/{$new->id}/cairkan")->assertSessionHasErrors('loan');

        $this->assertSame('Disetujui', $new->fresh()->status);            // tidak ada yang berubah
        $this->assertSame('Aktif', $old->fresh()->status);
        $this->assertEquals(4000000, $old->fresh()->outstanding);
        $this->assertSame(50000000.0, JournalLine::cashBalance());
    }

    public function test_payoff_is_recalculated_at_disbursement(): void
    {
        $old = $this->running(3000000);
        $this->apply('10.000.000')->assertSessionHasNoErrors();
        $new = Loan::where('id', '!=', $old->id)->firstOrFail();

        $old->update(['outstanding' => 1000000]);                         // angsuran masuk setelah pengajuan: kewajiban kini 1.010.417
        $this->approveAndDisburse($new)->assertSessionHasNoErrors();

        $this->assertSame(50000000.0 - (10000000 - 1010417), JournalLine::cashBalance());
    }

    public function test_cash_is_checked_against_the_net_amount_not_the_full_plafon(): void
    {
        $old = $this->running(3000000);
        // Kas 50 juta; plafon 52 juta melebihi kas, tetapi uang keluar bersih 52.000.000 - 3.031.250 = 48.968.750 masih cukup.
        $this->apply('52.000.000')->assertSessionHasNoErrors();
        $new = Loan::where('id', '!=', $old->id)->firstOrFail();
        $this->approveAndDisburse($new)->assertSessionHasNoErrors();
        $this->assertSame('Aktif', $new->fresh()->status);

        // Kas tinggal 1.031.250. Pinjaman berikutnya Rp 60 juta: kewajiban lama = pinjaman 52 juta yang baru dicairkan (52.541.667) + satu lagi (1.010.417),
        // jadi plafon cukup tetapi uang keluar bersih 6.447.916 melebihi kas. Ditolak karena kas, dan tidak ada pinjaman lama yang ikut dilunasi.
        $second = $this->running(1000000);
        $third = Loan::create(['member_id' => $this->member->id, 'loan_no' => 'PIN-3', 'product' => Loan::PRODUCT, 'principal' => 60000000, 'interest_rate' => 12.5,
            'term_months' => 12, 'installment' => 1, 'outstanding' => 0, 'status' => 'Disetujui', 'applied_at' => today()]);
        $cashBefore = JournalLine::cashBalance();
        $this->post("/pinjaman/{$third->id}/cairkan")->assertSessionHasErrors('loan');
        $this->assertStringContainsString('Saldo kas tidak cukup', session('errors')->first('loan'));
        $this->assertSame(['Disetujui', 'Aktif', 'Aktif'], [$third->fresh()->status, $second->fresh()->status, $new->fresh()->status]);
        $this->assertSame($cashBefore, JournalLine::cashBalance());
    }

    public function test_member_without_running_loans_is_disbursed_as_before(): void
    {
        $this->apply('10.000.000')->assertSessionHasNoErrors();
        $loan = Loan::firstOrFail();

        $this->approveAndDisburse($loan)->assertSessionHasNoErrors();

        $this->assertSame(40000000.0, JournalLine::cashBalance());
        $this->assertSame(0, Loan::whereNotNull('settled_by_loan_id')->count());
        $this->assertSame(1, Transaction::where('loan_id', $loan->id)->count());   // hanya pencairan, tanpa pelunasan
    }

    public function test_second_application_is_blocked_while_one_is_waiting(): void
    {
        $this->running(3000000);
        $this->apply('10.000.000')->assertSessionHasNoErrors();
        $this->apply('10.000.000')->assertSessionHasErrors('member_id');
        $this->assertSame(2, Loan::count());
    }

    public function test_form_shows_the_payoff_panel_data(): void
    {
        $old = $this->running(3000000, ['status' => 'Tunggakan', 'next_due_at' => today()->subDays(20)]);
        $this->actingAs($this->kasir);

        $page = $this->get('/pinjaman/ajukan')->assertOk()->assertSee('data-topup-panel', false);
        $data = json_decode(html_entity_decode($this->extract($page->getContent(), 'data-topups')), true);
        $this->assertSame($old->loan_no, $data[$this->member->id]['lines'][0]['no']);
        $this->assertEquals(3031250, $data[$this->member->id]['total']);
        $this->assertSame(20, $data[$this->member->id]['lines'][0]['days']);
    }

    private function extract(string $html, string $attribute): string
    {
        preg_match('/'.$attribute.'="([^"]*)"/', $html, $m);
        return $m[1] ?? '';
    }
}
