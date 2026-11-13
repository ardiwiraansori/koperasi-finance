<?php

namespace Tests\Feature;

use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Loan;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoanTest extends TestCase
{
    use RefreshDatabase;

    private Member $member;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::create(['role' => 'admin', 'name' => 'Admin', 'email' => 'a@x.test', 'password' => 'x']));
        $this->member = Member::create(['member_no' => 'AG000001', 'name' => 'Budi', 'branch' => 'Pusat', 'joined_at' => '2026-01-01', 'status' => 'Aktif']);
        $this->cash(50000000);
    }

    private function cash(float $amount): void
    {
        $j = JournalEntry::create(['journal_no' => 'JU-OPEN'.uniqid(), 'journal_date' => '2026-01-01', 'description' => 'Saldo awal', 'source' => 'AUTO JOURNAL', 'status' => 'Posted']);
        $j->lines()->create(['account_code' => '10101', 'account_name' => 'Kas Teller', 'debit' => $amount, 'credit' => 0]);
        $j->lines()->create(['account_code' => '39101', 'account_name' => 'Ekuitas Saldo Awal', 'debit' => 0, 'credit' => $amount]);
    }

    private function apply(array $override = [])
    {
        return $this->post('/pinjaman', $override + ['member_id' => $this->member->id, 'product' => 'Pinjaman Reguler', 'principal' => '10.000.000', 'term_months' => 12]);
    }

    private function loan(string $status, array $attrs = []): Loan
    {
        return Loan::create($attrs + [
            'member_id' => $this->member->id, 'loan_no' => 'PIN-'.uniqid(), 'product' => 'Pinjaman Reguler', 'principal' => 10000000, 'interest_rate' => 12,
            'term_months' => 12, 'installment' => Loan::annuity(10000000, 12, 12), 'outstanding' => 0, 'status' => $status, 'applied_at' => today(),
        ]);
    }

    public function test_application_computes_annuity_and_waits_for_approval(): void
    {
        $this->apply()->assertSessionHasNoErrors();

        $loan = Loan::firstOrFail();
        $this->assertSame(sprintf('PIN-%d-000001', now()->year), $loan->loan_no);
        $this->assertSame('Menunggu Approval', $loan->status);
        $this->assertEquals(12, $loan->interest_rate);                       // bunga mengikuti produk, bukan input pengguna
        $this->assertEquals(Loan::annuity(10000000, 12, 12), $loan->installment);
        $this->assertEquals(888488, $loan->installment);
        $this->assertEquals(0, $loan->outstanding);                           // belum dicairkan, belum ada sisa pokok
    }

    public function test_application_rules(): void
    {
        $this->apply(['principal' => '100.000'])->assertSessionHasErrors('principal');
        $this->apply(['term_months' => 2])->assertSessionHasErrors('term_months');
        $this->apply(['term_months' => 61])->assertSessionHasErrors('term_months');
        $this->apply(['product' => 'Pinjaman Palsu'])->assertSessionHasErrors('product');
        $this->assertSame(0, Loan::count());

        $this->apply()->assertSessionHasNoErrors();
        $this->apply()->assertSessionHasErrors('member_id');                  // masih ada pengajuan yang belum selesai
        $this->assertSame(1, Loan::count());

        Loan::query()->delete();
        $this->loan('Aktif', ['outstanding' => 5000000, 'next_due_at' => today()->subDays(5)]);
        $this->apply()->assertSessionHasErrors('member_id');                  // punya tunggakan

        Loan::query()->delete();
        $this->member->update(['status' => 'Nonaktif']);
        $this->apply()->assertSessionHasErrors('member_id');                  // anggota tidak aktif
    }

    public function test_approve_then_disburse_posts_receivable_journal_and_activates_loan(): void
    {
        $loan = $this->loan('Menunggu Approval');
        $this->post("/pinjaman/{$loan->id}/cairkan")->assertSessionHasErrors('loan'); // belum disetujui

        $this->post("/pinjaman/{$loan->id}/setujui")->assertSessionHasNoErrors();
        $this->assertSame('Disetujui', $loan->fresh()->status);

        $this->post("/pinjaman/{$loan->id}/cairkan")->assertSessionHasNoErrors();
        $loan->refresh();
        $this->assertSame('Aktif', $loan->status);
        $this->assertEquals(10000000, $loan->outstanding);
        $this->assertSame(today()->toDateString(), $loan->disbursed_at->toDateString());
        $this->assertSame(today()->addMonthNoOverflow()->toDateString(), $loan->next_due_at->toDateString());
        $this->assertSame(40000000.0, JournalLine::cashBalance());

        $lines = JournalEntry::with('lines')->latest('id')->first()->lines->mapWithKeys(fn ($l) => [$l->account_code => [(float) $l->debit, (float) $l->credit]])->all();
        $this->assertSame(['11301' => [10000000.0, 0.0], '10101' => [0.0, 10000000.0]], $lines);
        $this->assertDatabaseHas('transactions', ['loan_id' => $loan->id, 'category' => 'Pencairan', 'direction' => 'out']);

        $this->post("/pinjaman/{$loan->id}/cairkan")->assertSessionHasErrors('loan'); // tidak bisa dicairkan dua kali
        $this->assertSame(40000000.0, JournalLine::cashBalance());
    }

    public function test_disbursement_blocked_when_cash_is_insufficient(): void
    {
        $loan = $this->loan('Disetujui', ['principal' => 80000000, 'installment' => Loan::annuity(80000000, 12, 12)]); // kas hanya 50 juta

        $this->post("/pinjaman/{$loan->id}/cairkan")->assertSessionHasErrors('loan');
        $this->assertSame('Disetujui', $loan->fresh()->status);
        $this->assertSame(50000000.0, JournalLine::cashBalance());
    }

    public function test_reject_requires_reason_and_rejected_loan_cannot_be_approved(): void
    {
        $loan = $this->loan('Menunggu Approval');

        $this->post("/pinjaman/{$loan->id}/tolak", ['note' => ''])->assertSessionHasErrors('note');
        $this->assertSame('Menunggu Approval', $loan->fresh()->status);

        $this->post("/pinjaman/{$loan->id}/tolak", ['note' => 'Agunan belum lengkap'])->assertSessionHasNoErrors();
        $this->assertSame(['Ditolak', 'Agunan belum lengkap'], [$loan->fresh()->status, $loan->fresh()->note]);

        $this->post("/pinjaman/{$loan->id}/setujui");
        $this->assertSame('Ditolak', $loan->fresh()->status);
    }

    public function test_overdue_status_is_derived_from_due_date_and_recovers_after_installments(): void
    {
        $loan = $this->loan('Aktif', ['outstanding' => 10000000, 'disbursed_at' => today()->subMonths(2), 'next_due_at' => today()->subDays(40)]);
        $this->assertSame('Tunggakan', $loan->display_status);       // tampilan otomatis menunggak walau status tersimpan Aktif
        $this->assertSame(40, $loan->daysOverdue());

        $pay = fn () => $this->post('/kasir', ['category' => 'Angsuran', 'member_id' => $this->member->id, 'loan_id' => $loan->id, 'amount' => '888.488', 'description' => 'Angsuran']);
        $pay()->assertSessionHasNoErrors();
        $this->assertSame(['Tunggakan', true], [$loan->fresh()->status, $loan->fresh()->daysOverdue() > 0]); // jatuh tempo maju 1 bulan, masih lewat tempo ~9 hari

        $pay()->assertSessionHasNoErrors();
        $this->assertSame(['Aktif', 0], [$loan->fresh()->status, $loan->fresh()->daysOverdue()]);
        $this->assertDatabaseHas('transactions', ['loan_id' => $loan->id, 'category' => 'Angsuran']);
    }

    public function test_due_date_does_not_jump_over_a_short_month(): void
    {
        $loan = $this->loan('Aktif', ['outstanding' => 10000000, 'disbursed_at' => '2025-12-31', 'next_due_at' => '2026-01-31']);

        $this->post('/kasir', ['category' => 'Angsuran', 'member_id' => $this->member->id, 'loan_id' => $loan->id, 'amount' => '888.488', 'description' => 'Angsuran'])
            ->assertSessionHasNoErrors();

        $this->assertSame('2026-02-28', $loan->fresh()->next_due_at->toDateString());   // bukan 3 Maret
    }

    public function test_schedule_amortizes_to_zero(): void
    {
        $loan = $this->loan('Aktif', ['principal' => 25000000, 'installment' => Loan::annuity(25000000, 12, 24), 'term_months' => 24, 'disbursed_at' => today()]);
        $rows = $loan->schedule();

        $this->assertCount(24, $rows);
        $this->assertEquals(1176837, $loan->installment);              // sama dengan data awal koperasi
        $this->assertSame(0.0, $rows[23]['balance']);
        $this->assertEqualsWithDelta(25000000, array_sum(array_column($rows, 'principal')), 1);
    }

    public function test_pages_render_and_nav_counts_the_queue(): void
    {
        $loan = $this->loan('Menunggu Approval');
        $this->loan('Disetujui', ['loan_no' => 'PIN-X2']);
        $other = Member::create(['member_no' => 'AG000002', 'name' => 'Rina', 'branch' => 'Pusat', 'joined_at' => '2026-01-01', 'status' => 'Aktif']);

        $this->get('/pinjaman')->assertOk()->assertSee($loan->loan_no)->assertSee('Menunggu Pencairan');
        $this->get('/pinjaman/ajukan')->assertOk()->assertSee('Pengajuan Pinjaman');
        $this->get("/pinjaman/{$loan->id}")->assertOk()->assertSee('Jadwal Angsuran')->assertSee('Setujui');
        $this->get('/approval')->assertOk()->assertSee($loan->loan_no)->assertSee('PIN-X2')->assertSee('Cairkan');
        $this->get('/approval')->assertSee('<b class="nav-count" aria-label="2 menunggu">2</b>', false);
        $this->assertNotNull($other);
    }
}
