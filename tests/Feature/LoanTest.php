<?php

namespace Tests\Feature;

use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Loan;
use App\Models\Member;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoanTest extends TestCase
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
        $this->actingAs($this->admin);
        $this->member = Member::create(['member_no' => 'AG000001', 'name' => 'Budi', 'branch' => 'Pusat', 'joined_at' => '2026-01-01', 'status' => 'Aktif']);
        $this->cash(50000000);
    }

    private function cash(float $amount): void
    {
        $j = JournalEntry::create(['journal_no' => 'JU-OPEN'.uniqid(), 'journal_date' => '2026-01-01', 'description' => 'Saldo awal', 'source' => 'AUTO JOURNAL', 'status' => 'Posted']);
        $j->lines()->create(['account_code' => '10101', 'account_name' => 'Kas Teller', 'debit' => $amount, 'credit' => 0]);
        $j->lines()->create(['account_code' => '39101', 'account_name' => 'Ekuitas Saldo Awal', 'debit' => 0, 'credit' => $amount]);
    }

    // Hanya kasir yang mengajukan. Setelah ini pengguna aktif adalah kasir; panggil $this->actingAs($this->admin) untuk menyetujui.
    private function apply(array $override = [])
    {
        $this->actingAs($this->kasir);
        return $this->post('/pinjaman', $override + ['member_id' => $this->member->id, 'principal' => '10.000.000', 'term_months' => 12]);
    }

    // Pencairan dilakukan kasir. Setelah ini pengguna aktif adalah kasir; panggil $this->actingAs($this->admin) untuk menyetujui.
    private function disburse(Loan $loan)
    {
        $this->actingAs($this->kasir);
        return $this->post("/pinjaman/{$loan->id}/cairkan");
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
        $this->assertEquals(12.5, $loan->interest_rate);                      // satu produk, bunga tetap 12,5%
        $this->assertSame('Pinjaman Reguler', $loan->product);
        $this->assertEquals(Loan::annuity(10000000, 12.5, 12), $loan->installment);
        $this->assertEquals(890829, $loan->installment);
        $this->assertEquals(0, $loan->outstanding);                           // belum dicairkan, belum ada sisa pokok
        $this->assertSame($this->kasir->id, $loan->applied_by);               // pengaju tercatat
    }

    public function test_product_and_rate_cannot_be_chosen_by_the_applicant(): void
    {
        $this->apply(['product' => 'Pinjaman Usaha', 'interest_rate' => 1])->assertSessionHasNoErrors();

        $loan = Loan::firstOrFail();
        $this->assertSame(['Pinjaman Reguler', 12.5], [$loan->product, (float) $loan->interest_rate]);
    }

    public function test_only_kasir_may_apply_and_only_admin_may_decide(): void
    {
        $this->get('/pinjaman/ajukan')->assertForbidden();                                         // admin tidak mengajukan
        $this->post('/pinjaman', ['member_id' => $this->member->id, 'principal' => '10.000.000', 'term_months' => 12])->assertForbidden();
        $this->assertSame(0, Loan::count());

        $this->apply()->assertSessionHasNoErrors();
        $loan = Loan::firstOrFail();
        $this->post("/pinjaman/{$loan->id}/setujui")->assertForbidden();                           // kasir tidak menyetujui sendiri
        $this->assertSame('Menunggu Approval', $loan->fresh()->status);
    }

    public function test_approver_and_disburser_are_recorded(): void
    {
        $this->apply();
        $loan = Loan::firstOrFail();

        $this->actingAs($this->admin);
        $this->post("/pinjaman/{$loan->id}/setujui")->assertSessionHasNoErrors();
        $this->post("/pinjaman/{$loan->id}/cairkan")->assertForbidden();                            // admin menyetujui, tidak mencairkan
        $this->disburse($loan)->assertSessionHasNoErrors();                                         // kasir yang sama dengan pengaju boleh mencairkan

        $loan->refresh();
        $this->assertSame([$this->kasir->id, $this->admin->id, $this->kasir->id], [$loan->applied_by, $loan->approved_by, $loan->disbursed_by]);
        $this->get("/pinjaman/{$loan->id}")->assertOk()->assertSee('Diajukan oleh Kasir')->assertSee('Disetujui oleh Admin')->assertSee('Dicairkan oleh Kasir');
    }

    public function test_deleting_a_user_keeps_the_loan_history(): void
    {
        $this->apply();
        $loan = Loan::firstOrFail();

        $this->kasir->delete();

        $this->assertNull($loan->fresh()->applied_by);   // pinjaman tetap ada, hanya nama pengajunya yang kosong
    }

    public function test_duplicate_loan_number_is_retried_instead_of_failing(): void
    {
        // Simulasi dua pengajuan bersamaan: percobaan pertama ditolak kolom unik, yang kedua harus berhasil.
        $attempts = 0;
        Loan::creating(function () use (&$attempts) {
            if (++$attempts === 1) {
                throw new UniqueConstraintViolationException('sqlite', 'insert into loans', [], new \Exception('UNIQUE constraint failed: loans.loan_no'));
            }
        });

        $this->apply()->assertSessionHasNoErrors();

        $this->assertSame(2, $attempts);
        $this->assertSame(1, Loan::count());   // tidak ada pinjaman ganda dari percobaan yang gagal
    }

    public function test_loan_number_failure_is_not_retried_forever(): void
    {
        $attempts = 0;
        Loan::creating(function () use (&$attempts) {
            $attempts++;
            throw new UniqueConstraintViolationException('sqlite', 'insert into loans', [], new \Exception('UNIQUE constraint failed: loans.loan_no'));
        });

        $this->withoutExceptionHandling();
        $this->expectException(UniqueConstraintViolationException::class);
        try {
            $this->apply();
        } finally {
            $this->assertSame(3, $attempts);   // berhenti setelah 3 kali, galat tetap dilaporkan
        }
    }

    public function test_application_rules(): void
    {
        $this->apply(['principal' => '100.000'])->assertSessionHasErrors('principal');
        $this->apply(['term_months' => 2])->assertSessionHasErrors('term_months');
        $this->apply(['term_months' => 61])->assertSessionHasErrors('term_months');
        $this->assertSame(0, Loan::count());

        $this->apply()->assertSessionHasNoErrors();
        $this->apply()->assertSessionHasErrors('member_id');                  // masih ada pengajuan yang belum selesai
        $this->assertSame(1, Loan::count());

        Loan::query()->delete();
        $this->loan('Aktif', ['outstanding' => 5000000, 'next_due_at' => today()->subDays(5)]);
        $this->apply()->assertSessionHasNoErrors();                           // menunggak boleh mengajukan, lewat top-up (dilunasi saat pencairan)

        Loan::query()->delete();
        $this->member->update(['status' => 'Nonaktif']);
        $this->apply()->assertSessionHasErrors('member_id');                  // anggota tidak aktif
    }

    public function test_approve_then_disburse_posts_receivable_journal_and_activates_loan(): void
    {
        $loan = $this->loan('Menunggu Approval');
        $this->disburse($loan)->assertSessionHasErrors('loan'); // belum disetujui

        $this->actingAs($this->admin);
        $this->post("/pinjaman/{$loan->id}/setujui")->assertSessionHasNoErrors();
        $this->assertSame('Disetujui', $loan->fresh()->status);

        $this->disburse($loan)->assertSessionHasNoErrors();
        $loan->refresh();
        $this->assertSame('Aktif', $loan->status);
        $this->assertEquals(10000000, $loan->outstanding);
        $this->assertSame(today()->toDateString(), $loan->disbursed_at->toDateString());
        $this->assertSame(today()->addMonthNoOverflow()->toDateString(), $loan->next_due_at->toDateString());
        $this->assertSame(40000000.0, JournalLine::cashBalance());

        $lines = JournalEntry::with('lines')->latest('id')->first()->lines->mapWithKeys(fn ($l) => [$l->account_code => [(float) $l->debit, (float) $l->credit]])->all();
        $this->assertSame(['11301' => [10000000.0, 0.0], '10101' => [0.0, 10000000.0]], $lines);
        $this->assertDatabaseHas('transactions', ['loan_id' => $loan->id, 'category' => 'Pencairan', 'direction' => 'out']);

        $this->disburse($loan)->assertSessionHasErrors('loan'); // tidak bisa dicairkan dua kali
        $this->assertSame(40000000.0, JournalLine::cashBalance());
    }

    public function test_disbursement_blocked_when_cash_is_insufficient(): void
    {
        $loan = $this->loan('Disetujui', ['principal' => 80000000, 'installment' => Loan::annuity(80000000, 12, 12)]); // kas hanya 50 juta

        $this->disburse($loan)->assertSessionHasErrors('loan');
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
        $this->get("/pinjaman/{$loan->id}")->assertOk()->assertSee('Jadwal Angsuran')->assertSee('Setujui');
        $this->get('/approval')->assertOk()->assertSee($loan->loan_no)->assertSee('PIN-X2')->assertSee('Menunggu dicairkan kasir');
        $this->get('/approval')->assertSee('<b class="nav-count" aria-label="1 menunggu">1</b>', false);   // admin: hanya yang perlu disetujui
        $this->assertNotNull($other);

        $this->actingAs($this->kasir);
        $this->get('/pinjaman/ajukan')->assertOk()->assertSee('Pengajuan Pinjaman')->assertSee('Pinjaman Reguler')->assertSee('12,5% per tahun');
    }
}
