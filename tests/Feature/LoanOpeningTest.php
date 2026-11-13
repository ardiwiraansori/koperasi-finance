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

class LoanOpeningTest extends TestCase
{
    use RefreshDatabase;

    private Member $member;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::create(['role' => 'admin', 'name' => 'Admin', 'email' => 'a@x.test', 'password' => 'x']));
        $this->member = Member::create(['member_no' => 'AG000001', 'name' => 'Budi', 'branch' => 'Pusat', 'joined_at' => '2020-01-01', 'status' => 'Aktif']);
        $j = JournalEntry::create(['journal_no' => 'JU-OPEN', 'journal_date' => '2020-01-01', 'description' => 'Saldo awal', 'source' => 'AUTO JOURNAL', 'status' => 'Posted']);
        $j->lines()->create(['account_code' => '10101', 'account_name' => 'Kas Teller', 'debit' => 5000000, 'credit' => 0]);
        $j->lines()->create(['account_code' => '39101', 'account_name' => 'Ekuitas Saldo Awal', 'debit' => 0, 'credit' => 5000000]);
    }

    private function opening(array $override = [])
    {
        return $this->post('/pinjaman/saldo-awal', $override + [
            'member_id' => $this->member->id, 'product' => 'Pinjaman Reguler', 'principal' => '10.000.000', 'term_months' => 12, 'outstanding' => '6.000.000',
            'disbursed_at' => today()->subMonths(6)->toDateString(), 'next_due_at' => today()->addDays(10)->toDateString(), 'tanggal' => today()->toDateString(),
        ]);
    }

    public function test_opening_loan_is_recorded_as_receivable_without_touching_cash(): void
    {
        $this->opening()->assertSessionHasNoErrors();

        $loan = Loan::firstOrFail();
        $this->assertSame(['Aktif', 12.0, 'Pinjaman Reguler'], [$loan->status, (float) $loan->interest_rate, $loan->product]);
        $this->assertEquals(6000000, $loan->outstanding);
        $this->assertEquals(Loan::annuity(10000000, 12, 12), $loan->installment);              // cicilan otomatis
        $this->assertSame(today()->subMonths(6)->toDateString(), $loan->disbursed_at->toDateString());
        $this->assertStringStartsWith('PIN-', $loan->loan_no);

        $this->assertSame(5000000.0, JournalLine::cashBalance());                               // kas TIDAK berubah
        $trx = Transaction::where('loan_id', $loan->id)->firstOrFail();
        $this->assertSame(['Saldo Awal Pinjaman', 'in'], [$trx->category, $trx->direction]);
        $lines = JournalEntry::with('lines')->where('reference', $trx->trx_no)->firstOrFail()->lines->mapWithKeys(fn ($l) => [$l->account_code => [(float) $l->debit, (float) $l->credit]])->all();
        $this->assertSame(['11301' => [6000000.0, 0.0], '39101' => [0.0, 6000000.0]], $lines);   // D Piutang / K Ekuitas Saldo Awal
    }

    public function test_opening_loan_is_not_cash_flow_and_shows_a_remaining_schedule(): void
    {
        $this->opening()->assertSessionHasNoErrors();
        $loan = Loan::firstOrFail();

        $this->get('/kasir')->assertOk()->assertDontSee(Transaction::where('loan_id', $loan->id)->value('trx_no'));   // bukan aktivitas kas
        $dash = $this->get('/dashboard')->assertOk();
        $this->assertSame(0.0, (float) $dash->viewData('stats')['income']);
        $this->assertEquals(6000000, $dash->viewData('stats')['loans']);                        // tetap terhitung sebagai pinjaman beredar

        $page = $this->get("/pinjaman/{$loan->id}")->assertOk()->assertSee('Jadwal Sisa Angsuran')->assertSee('Saldo awal pinjaman');
        $rows = $loan->remainingSchedule();
        $this->assertEqualsWithDelta(6000000, array_sum(array_column($rows, 'principal')), 1);  // sisa jadwal melunasi sisa pokok
        $this->assertSame(0.0, end($rows)['balance']);
        $this->assertSame($loan->next_due_at->toDateString(), $rows[0]['due']->toDateString()); // mulai dari jatuh tempo berikutnya
        $this->assertNotNull($page);
    }

    public function test_installments_after_opening_reduce_the_opening_balance(): void
    {
        $this->opening()->assertSessionHasNoErrors();
        $loan = Loan::firstOrFail();
        $due = $loan->next_due_at->copy();

        $this->post('/kasir', ['category' => 'Angsuran', 'member_id' => $this->member->id, 'loan_id' => $loan->id, 'amount' => (string) (int) $loan->installment, 'description' => 'Angsuran'])
            ->assertSessionHasNoErrors();

        $loan->refresh();
        $interest = round(6000000 * 12 / 1200);
        $this->assertEquals(6000000 - ((int) $loan->installment - $interest), $loan->outstanding);
        $this->assertSame($due->addMonth()->toDateString(), $loan->next_due_at->toDateString());
        $this->assertGreaterThan(5000000, JournalLine::cashBalance());                          // angsuran sungguhan menambah kas
    }

    public function test_custom_name_installment_and_overdue_due_date(): void
    {
        $this->opening(['loan_no' => 'LAMA-001', 'installment' => '950.000', 'next_due_at' => today()->subDays(15)->toDateString()])->assertSessionHasNoErrors();
        $loan = Loan::where('loan_no', 'LAMA-001')->firstOrFail();

        $this->assertEquals(950000, $loan->installment);                                        // cicilan dari catatan lama dipakai
        $this->assertSame('Tunggakan', $loan->display_status);                                  // jatuh tempo sudah lewat
        $this->assertSame(15, $loan->daysOverdue());
    }

    public function test_validation(): void
    {
        $before = Transaction::count();
        $this->opening(['outstanding' => '11.000.000'])->assertSessionHasErrors('outstanding');       // sisa > plafon
        $this->opening(['outstanding' => ''])->assertSessionHasErrors('outstanding');
        $this->opening(['principal' => ''])->assertSessionHasErrors('principal');
        $this->opening(['product' => 'Palsu'])->assertSessionHasErrors('product');
        $this->opening(['term_months' => 0])->assertSessionHasErrors('term_months');
        $this->opening(['term_months' => 121])->assertSessionHasErrors('term_months');
        $this->opening(['member_id' => ''])->assertSessionHasErrors('member_id');
        $this->opening(['disbursed_at' => today()->addDay()->toDateString()])->assertSessionHasErrors('disbursed_at');
        $this->opening(['next_due_at' => today()->subYear()->toDateString()])->assertSessionHasErrors('next_due_at');   // sebelum pencairan
        $this->opening(['tanggal' => today()->addDay()->toDateString()])->assertSessionHasErrors('tanggal');
        $this->assertSame(0, Loan::count());
        $this->assertSame($before, Transaction::count());

        $this->opening(['loan_no' => 'LAMA-001'])->assertSessionHasNoErrors();
        $this->opening(['loan_no' => 'LAMA-001'])->assertSessionHasErrors('loan_no');               // nomor kembar
        $this->assertSame(1, Loan::count());
    }

    public function test_kasir_cannot_record_opening_loans_and_pages_render(): void
    {
        $this->get('/pinjaman/saldo-awal')->assertOk()->assertSee('Saldo Awal Pinjaman')->assertSee('data-combobox="member_id"', false);
        $this->get('/pinjaman')->assertSee(route('loans.opening'), false);

        $this->actingAs(User::create(['role' => 'kasir', 'name' => 'Kasir', 'email' => 'k@x.test', 'password' => 'x']));
        $this->opening()->assertForbidden();
        $this->get('/pinjaman/saldo-awal')->assertForbidden();
        $this->get('/pinjaman')->assertOk()->assertDontSee(route('loans.opening'), false);
        $this->assertSame(0, Loan::count());
    }

    public function test_remaining_schedule_never_loops_when_installment_does_not_cover_interest(): void
    {
        $this->opening(['installment' => '1.000'])->assertSessionHasNoErrors();                    // bunga 60.000/bulan > cicilan 1.000
        $this->assertSame([], Loan::firstOrFail()->remainingSchedule());
        $this->get('/pinjaman/'.Loan::firstOrFail()->id)->assertOk();
    }

    public function test_balance_sheet_reconciles_after_opening_loan(): void
    {
        $this->opening()->assertSessionHasNoErrors();
        $rec = collect($this->get('/laporan/neraca')->assertOk()->viewData('reconciliation'))->keyBy('code');
        $this->assertEquals(0, $rec['11301']['diff']);                                              // piutang di jurnal = sisa pokok pinjaman
        $this->assertTrue(\App\Services\Reports::balanceSheet(today()->toDateString())['balanced']);
    }
}
