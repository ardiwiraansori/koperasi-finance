<?php

namespace Tests\Feature;

use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Loan;
use App\Models\Member;
use App\Models\Saving;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MoneyTest extends TestCase
{
    use RefreshDatabase;

    private Member $member;
    /** @var array<string, Saving> */
    private array $acc = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::create(['role' => 'admin', 'name' => 'Admin', 'email' => 'a@x.test', 'password' => 'x']));
        $this->member = Member::create(['member_no' => 'AG000001', 'name' => 'Budi', 'branch' => 'Pusat', 'joined_at' => '2026-01-01', 'status' => 'Aktif']);
        // Saldo awal kas Rp 10.000.000 supaya transaksi biasa tidak tertahan pengecekan kas.
        $open = JournalEntry::create(['journal_no' => 'JU-OPEN', 'journal_date' => '2026-01-01', 'description' => 'Saldo awal', 'source' => 'AUTO JOURNAL', 'status' => 'Posted']);
        $open->lines()->create(['account_code' => '10101', 'account_name' => 'Kas Teller', 'debit' => 10000000, 'credit' => 0]);
        $open->lines()->create(['account_code' => '39101', 'account_name' => 'Ekuitas Saldo Awal', 'debit' => 0, 'credit' => 10000000]);
        foreach (['Pokok', 'Wajib', 'Sukarela'] as $i => $type) {
            $this->acc[$type] = $this->member->savings()->create(['account_no' => 'SAV-0001-'.($i + 1), 'type' => $type]);
        }
    }

    private function type(string $name): int
    {
        return \App\Models\TransactionType::where('name', $name)->value('id');
    }

    private function deposit(string $type, string $amount)
    {
        return $this->post('/simpanan/setoran', ['saving_id' => $this->acc[$type]->id, 'amount' => $amount]);
    }

    private function withdraw(string $type, string $amount)
    {
        return $this->post('/simpanan/penarikan', ['saving_id' => $this->acc[$type]->id, 'amount' => $amount]);
    }

    private function loan(float $outstanding = 1000000, float $installment = 300000): Loan
    {
        return Loan::create([
            'member_id' => $this->member->id, 'loan_no' => 'PIN-1', 'principal' => $outstanding, 'interest_rate' => 12,
            'term_months' => 12, 'installment' => $installment, 'outstanding' => $outstanding, 'status' => 'Aktif',
            'applied_at' => '2026-01-01', 'next_due_at' => '2026-12-01',
        ]);
    }

    /** @return array<string, array{float,float}> kode akun => [debit, kredit] dari jurnal terakhir; sekaligus cek seimbang */
    private function lastJournal(): array
    {
        $lines = JournalEntry::with('lines')->latest('id')->firstOrFail()->lines;
        $this->assertEquals($lines->sum('debit'), $lines->sum('credit'), 'jurnal harus seimbang');
        return $lines->mapWithKeys(fn ($l) => [$l->account_code => [(float) $l->debit, (float) $l->credit]])->all();
    }

    public function test_deposit_formatted_amount_updates_balance_and_posts_equity_journal(): void
    {
        $this->deposit('Wajib', '1.500.000')->assertSessionHasNoErrors();

        $this->assertEquals(1500000, $this->acc['Wajib']->fresh()->balance);
        $this->assertSame(['10101' => [1500000.0, 0.0], '31102' => [0.0, 1500000.0]], $this->lastJournal());
    }

    public function test_sukarela_is_a_liability_and_pokok_can_be_paid_only_once(): void
    {
        $this->deposit('Sukarela', '100000');
        $this->assertArrayHasKey('20201', $this->lastJournal());

        $this->deposit('Pokok', '500000')->assertSessionHasNoErrors();
        $this->deposit('Pokok', '500000')->assertSessionHasErrors('saving_id');
        $this->assertEquals(500000, $this->acc['Pokok']->fresh()->balance);
    }

    public function test_deposit_rejected_for_inactive_member_and_bad_amounts(): void
    {
        $this->deposit('Sukarela', '0')->assertSessionHasErrors('amount');
        $this->deposit('Sukarela', 'abc')->assertSessionHasErrors('amount');
        $this->deposit('Sukarela', '2.000.000.000')->assertSessionHasErrors('amount');

        $this->member->update(['status' => 'Nonaktif']);
        $this->deposit('Sukarela', '1000')->assertSessionHasErrors('saving_id');
        $this->assertEquals(0, $this->acc['Sukarela']->fresh()->balance);
    }

    public function test_withdrawal_rules(): void
    {
        $this->deposit('Sukarela', '1000000');
        $this->deposit('Wajib', '300000');

        $this->withdraw('Sukarela', '400000')->assertSessionHasNoErrors();
        $this->assertEquals(600000, $this->acc['Sukarela']->fresh()->balance);
        $this->assertSame(['20201' => [400000.0, 0.0], '10101' => [0.0, 400000.0]], $this->lastJournal());

        $this->withdraw('Sukarela', '700000')->assertSessionHasErrors('saving_id'); // melebihi saldo
        $this->withdraw('Wajib', '300000')->assertSessionHasErrors('saving_id');    // masih anggota aktif

        $this->member->update(['status' => 'Nonaktif']);                            // sudah keluar
        $this->withdraw('Wajib', '300000')->assertSessionHasNoErrors();
        $this->assertEquals(0, $this->acc['Wajib']->fresh()->balance);
    }

    public function test_withdrawal_blocked_while_member_has_running_loan(): void
    {
        $this->deposit('Sukarela', '1000000');
        $loan = $this->loan();

        $this->withdraw('Sukarela', '100000')->assertSessionHasErrors('saving_id');
        $this->assertEquals(1000000, $this->acc['Sukarela']->fresh()->balance);

        $loan->update(['status' => 'Lunas', 'outstanding' => 0]);
        $this->withdraw('Sukarela', '100000')->assertSessionHasNoErrors();
    }

    public function test_installment_splits_interest_and_principal_and_posts_balanced_journal(): void
    {
        $loan = $this->loan(1000000, 300000); // bunga bulan ini = 1.000.000 x 12% / 12 = 10.000

        $this->post('/kasir', ['category' => 'Angsuran', 'member_id' => $this->member->id, 'loan_id' => $loan->id, 'amount' => '300.000', 'description' => 'Angsuran'])
            ->assertSessionHasNoErrors();

        $loan->refresh();
        $this->assertEquals(710000, $loan->outstanding);                       // 1.000.000 - (300.000 - 10.000)
        $this->assertSame('2027-01-01', $loan->next_due_at->toDateString());   // angsuran penuh: jatuh tempo maju sebulan
        $this->assertSame(['10101' => [300000.0, 0.0], '11301' => [0.0, 290000.0], '41101' => [0.0, 10000.0]], $this->lastJournal());
    }

    public function test_installment_rejects_overpayment_and_marks_loan_paid_off(): void
    {
        $loan = $this->loan(1000000, 300000);
        $pay = fn (string $amt) => $this->post('/kasir', ['category' => 'Angsuran', 'member_id' => $this->member->id, 'loan_id' => $loan->id, 'amount' => $amt, 'description' => 'x']);

        $pay('1.010.001')->assertSessionHasErrors('loan_id'); // lebih dari sisa pokok + bunga
        $pay('5.000')->assertSessionHasErrors('loan_id');     // kurang dari bunga bulan ini
        $pay('1.010.000')->assertSessionHasNoErrors();

        $loan->refresh();
        $this->assertSame('Lunas', $loan->status);
        $this->assertEquals(0, $loan->outstanding);
    }

    public function test_kasir_rejects_simpanan_category_in_indonesian(): void
    {
        $this->post('/kasir', ['category' => 'Simpanan', 'direction' => 'in', 'amount' => '1000', 'description' => 'x'])
            ->assertSessionHasErrors(['category' => 'Kategori tidak valid.']);
    }

    public function test_kasir_penerimaan_posts_income_journal_and_pengeluaran_posts_expense_journal(): void
    {
        $this->post('/kasir', ['category' => 'Penerimaan', 'jenis_id' => $this->type('Denda keterlambatan'), 'amount' => '25.000', 'description' => 'Denda telat'])
            ->assertSessionHasNoErrors();
        $this->assertSame(['10101' => [25000.0, 0.0], '41301' => [0.0, 25000.0]], $this->lastJournal());
        $this->assertDatabaseHas('transactions', ['category' => 'Penerimaan', 'direction' => 'in', 'amount' => 25000]);

        $this->post('/kasir', ['category' => 'Pengeluaran', 'jenis_id' => $this->type('Gaji dan honor'), 'amount' => '1.250.000', 'description' => 'Honor pengurus'])
            ->assertSessionHasNoErrors();
        $this->assertSame(['51201' => [1250000.0, 0.0], '10101' => [0.0, 1250000.0]], $this->lastJournal());
        $this->assertDatabaseHas('transactions', ['category' => 'Pengeluaran', 'direction' => 'out', 'amount' => 1250000]);
    }

    public function test_kasir_account_must_match_category(): void
    {
        $base = ['amount' => '1000', 'description' => 'x'];
        $before = JournalEntry::count();
        $this->post('/kasir', $base + ['category' => 'Penerimaan'])->assertSessionHasErrors('jenis_id');                              // jenis wajib
        $this->post('/kasir', $base + ['category' => 'Penerimaan', 'jenis_id' => $this->type('Biaya operasional kantor')])->assertSessionHasErrors('jenis_id'); // jenis pengeluaran untuk penerimaan
        $this->post('/kasir', $base + ['category' => 'Pengeluaran', 'jenis_id' => $this->type('Denda keterlambatan')])->assertSessionHasErrors('jenis_id');
        $this->assertSame($before, JournalEntry::count());
    }

    public function test_installment_rejects_loan_of_another_member(): void
    {
        $loan = $this->loan();
        $other = Member::create(['member_no' => 'AG000009', 'name' => 'Lain', 'branch' => 'Pusat', 'joined_at' => '2026-01-01', 'status' => 'Aktif']);

        $this->post('/kasir', ['category' => 'Angsuran', 'member_id' => $other->id, 'loan_id' => $loan->id, 'amount' => '300000', 'description' => 'x'])
            ->assertSessionHasErrors('loan_id');
        $this->assertEquals(1000000, $loan->fresh()->outstanding);
    }

    public function test_installment_requires_a_member(): void
    {
        $loan = $this->loan();
        $this->post('/kasir', ['category' => 'Angsuran', 'loan_id' => $loan->id, 'amount' => '300000', 'description' => 'x'])
            ->assertSessionHasErrors('member_id');
        $this->assertEquals(1000000, $loan->fresh()->outstanding);
    }

    public function test_opening_cash_balance_can_be_recorded_only_once_and_is_not_income(): void
    {
        $post = fn () => $this->post('/kasir', ['category' => 'Saldo Awal', 'amount' => '50.000.000', 'description' => 'Saldo awal kas']);

        $post()->assertSessionHasNoErrors();
        $this->assertSame(['10101' => [50000000.0, 0.0], '39101' => [0.0, 50000000.0]], $this->lastJournal());
        $this->assertSame(60000000.0, JournalLine::cashBalance());

        $post()->assertSessionHasErrors('category');
        $this->assertSame(60000000.0, JournalLine::cashBalance());

        $this->get('/dashboard')->assertOk()->assertSee('Rp 60.000.000')
            ->assertViewHas('stats', fn ($s) => (float) $s['income'] === 0.0); // saldo awal bukan kas masuk operasional
    }

    public function test_cash_outflows_cannot_exceed_cash_balance(): void
    {
        $out = fn (string $amt) => $this->post('/kasir', ['category' => 'Pengeluaran', 'jenis_id' => $this->type('Biaya operasional kantor'), 'amount' => $amt, 'description' => 'x']);
        $out('20.000.000')->assertSessionHasErrors('amount'); // kas hanya Rp 10.000.000
        $out('5.000.000')->assertSessionHasNoErrors();
        $this->assertSame(5000000.0, JournalLine::cashBalance());

        $this->acc['Sukarela']->update(['balance' => 30000000]);
        $this->withdraw('Sukarela', '20.000.000')->assertSessionHasErrors('saving_id'); // saldo cukup, kas tidak
        $this->assertEquals(30000000, $this->acc['Sukarela']->fresh()->balance);
    }

    public function test_statement_shows_movements_with_running_balance_and_period_filter(): void
    {
        $this->deposit('Sukarela', '1.000.000');
        $this->withdraw('Sukarela', '400.000');
        $id = $this->acc['Sukarela']->id;

        $res = $this->get("/simpanan/rekening-koran?rekening=$id")->assertOk()->assertSee('Setoran simpanan Sukarela AG000001');
        $st = $res->viewData('statement');
        $this->assertSame([0.0, 1000000.0, 400000.0, 600000.0, 2], [$st['opening'], $st['in'], $st['out'], $st['closing'], $st['rows']->count()]);
        $this->assertSame([1000000.0, 600000.0], $st['rows']->pluck('saldo')->all());

        $later = $this->get("/simpanan/rekening-koran?rekening=$id&dari=".now()->addDay()->toDateString())->viewData('statement');
        $this->assertSame([600000.0, 600000.0, 0], [$later['opening'], $later['closing'], $later['rows']->count()]);

        $this->get("/simpanan/rekening-koran?rekening=$id&dari=2026-12-31&sampai=2026-01-01")->assertSessionHasErrors('sampai');
    }

    public function test_new_member_can_pay_simpanan_pokok_at_registration(): void
    {
        $base = ['name' => 'Baru', 'branch' => 'Pusat', 'status' => 'Aktif', 'joined_at' => '2026-02-01'];

        $this->post('/anggota', $base + ['simpanan_pokok' => '500.000'])->assertSessionHasNoErrors();
        $new = Member::where('name', 'Baru')->firstOrFail();
        $pokok = $new->savings()->where('type', 'Pokok')->first();

        $this->assertEquals(500000, $pokok->balance);
        $this->assertEquals(0, $new->savings()->whereIn('type', ['Wajib', 'Sukarela'])->sum('balance'));
        $this->assertDatabaseHas('transactions', ['saving_id' => $pokok->id, 'category' => 'Simpanan', 'direction' => 'in', 'amount' => 500000]);
        $this->assertSame(['10101' => [500000.0, 0.0], '31101' => [0.0, 500000.0]], $this->lastJournal());
        $this->assertSame(10500000.0, JournalLine::cashBalance());
        // Setoran Pokok kedua lewat menu Simpanan ditolak (sudah dibayar saat pendaftaran).
        $this->post('/simpanan/setoran', ['saving_id' => $pokok->id, 'amount' => '100000'])->assertSessionHasErrors('saving_id');
    }

    public function test_simpanan_pokok_at_registration_is_optional_and_validated(): void
    {
        $base = ['name' => 'Baru', 'branch' => 'Pusat', 'status' => 'Aktif', 'joined_at' => '2026-02-01'];

        $this->post('/anggota', $base)->assertSessionHasNoErrors();                                   // boleh dikosongkan
        $this->assertSame(0, \App\Models\Transaction::where('category', 'Simpanan')->count());

        $this->post('/anggota', ['name' => 'Dua'] + $base + ['simpanan_pokok' => 'abc'])->assertSessionHasNoErrors(); // huruf dibuang -> kosong
        $this->post('/anggota', ['name' => 'Tiga', 'status' => 'Nonaktif', 'simpanan_pokok' => '100.000'] + $base)->assertSessionHasErrors('simpanan_pokok');
        $this->post('/anggota', ['name' => 'Empat'] + $base + ['simpanan_pokok' => '2.000.000.000'])->assertSessionHasErrors('simpanan_pokok');
        $this->assertNull(Member::where('name', 'Tiga')->first());
    }

    public function test_member_export_is_csv_and_neutralizes_spreadsheet_formulas(): void
    {
        Member::create(['member_no' => 'AG000077', 'name' => '=HYPERLINK("http://x")', 'branch' => 'Pusat', 'joined_at' => '2026-01-01', 'status' => 'Aktif']);

        $res = $this->get('/anggota/export')->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $csv = $res->streamedContent();

        $this->assertStringContainsString('"No Anggota";Nama;Telepon', $csv);
        $this->assertStringContainsString('AG000001;Budi', $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv);   // diawali ' supaya tidak dijalankan Excel sebagai rumus
        $this->assertStringNotContainsString(';=HYPERLINK', $csv);
    }

    public function test_new_member_gets_three_zero_balance_savings_accounts(): void
    {
        $this->post('/anggota', ['name' => 'Baru', 'branch' => 'Pusat', 'status' => 'Aktif', 'joined_at' => '2026-02-01'])->assertSessionHasNoErrors();

        $new = Member::where('name', 'Baru')->firstOrFail();
        $this->assertSame('AG000002', $new->member_no);
        $this->assertSame(['Pokok', 'Wajib', 'Sukarela'], $new->savings()->orderBy('id')->pluck('type')->all());
        $this->assertEquals(0, $new->savings()->sum('balance'));
    }
}
