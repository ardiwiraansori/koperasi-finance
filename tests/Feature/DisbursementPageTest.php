<?php

namespace Tests\Feature;

use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Loan;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Halaman Pencairan: kasir menyerahkan uang untuk pinjaman yang sudah disetujui admin.
class DisbursementPageTest extends TestCase
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
        $j = JournalEntry::create(['journal_no' => 'JU-OPEN', 'journal_date' => '2026-01-01', 'description' => 'Saldo awal', 'source' => 'AUTO JOURNAL', 'status' => 'Posted']);
        $j->lines()->create(['account_code' => '10101', 'account_name' => 'Kas Teller', 'debit' => 20000000, 'credit' => 0]);
        $j->lines()->create(['account_code' => '39101', 'account_name' => 'Ekuitas Saldo Awal', 'debit' => 0, 'credit' => 20000000]);
    }

    private function loan(string $status, array $attrs = []): Loan
    {
        return Loan::create($attrs + [
            'member_id' => $this->member->id, 'loan_no' => 'PIN-'.uniqid(), 'product' => Loan::PRODUCT, 'principal' => 10000000, 'interest_rate' => 12.5,
            'term_months' => 12, 'installment' => Loan::annuity(10000000, 12.5, 12), 'outstanding' => 0, 'status' => $status, 'applied_at' => today(),
            'applied_by' => $this->kasir->id, 'approved_by' => $this->admin->id,
        ]);
    }

    public function test_kasir_sees_only_approved_loans_with_the_cash_to_hand_over(): void
    {
        $approved = $this->loan('Disetujui', ['loan_no' => 'PIN-SIAP']);
        $this->loan('Menunggu Approval', ['loan_no' => 'PIN-BELUM']);
        $this->actingAs($this->kasir);

        $this->get('/pencairan')->assertOk()->assertSee('PIN-SIAP')->assertDontSee('PIN-BELUM')
            ->assertSee('Rp 10.000.000')->assertSee('Saldo kas Rp 20.000.000')->assertSee('disetujui Admin')
            ->assertSee('<b class="nav-count" aria-label="1 siap dicairkan">1</b>', false)
            ->assertSee(route('loans.disburse', $approved), false);
    }

    public function test_topup_loan_shows_the_payoff_and_the_net_cash_to_hand_over(): void
    {
        Loan::create(['member_id' => $this->member->id, 'loan_no' => 'PIN-LAMA', 'product' => Loan::PRODUCT, 'principal' => 10000000, 'interest_rate' => 12.5, 'term_months' => 12,
            'installment' => 1, 'outstanding' => 3000000, 'status' => 'Aktif', 'applied_at' => today()->subMonths(6), 'disbursed_at' => today()->subMonths(6), 'next_due_at' => today()->addDays(10)]);
        $this->loan('Disetujui');
        $this->actingAs($this->kasir);

        $this->get('/pencairan')->assertOk()->assertSee('Top-up: pelunasan pinjaman lama')->assertSee('PIN-LAMA')
            ->assertSee('Uang yang diserahkan ke anggota: <strong>Rp 6.968.750</strong>', false);
    }

    public function test_page_warns_when_cash_is_not_enough_and_shows_an_empty_state(): void
    {
        $this->actingAs($this->kasir);
        $this->get('/pencairan')->assertOk()->assertSee('Tidak ada pinjaman yang menunggu dicairkan');

        $this->loan('Disetujui', ['principal' => 30000000]);
        $this->get('/pencairan')->assertOk()->assertSee('Saldo kas tidak cukup');
    }

    public function test_kasir_disburses_from_the_page_and_the_queue_empties(): void
    {
        $loan = $this->loan('Disetujui');
        $this->actingAs($this->kasir);

        $this->post(route('loans.disburse', $loan))->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->assertSame(['Aktif', $this->kasir->id], [$loan->fresh()->status, $loan->fresh()->disbursed_by]);
        $this->assertSame(10000000.0, JournalLine::cashBalance());
        $this->get('/pencairan')->assertOk()->assertSee('Tidak ada pinjaman yang menunggu dicairkan');
    }

    public function test_admin_cannot_open_the_page_or_disburse_but_sees_the_waiting_status(): void
    {
        $loan = $this->loan('Disetujui');
        $this->actingAs($this->admin);

        $this->get('/pencairan')->assertForbidden();
        $this->post(route('loans.disburse', $loan))->assertForbidden();
        $this->get('/approval')->assertOk()->assertSee('Menunggu dicairkan kasir')->assertDontSee(route('loans.disburse', $loan), false);
        $this->get("/pinjaman/{$loan->id}")->assertOk()->assertDontSee('Cairkan Dana')->assertSee('Tolak');   // admin masih bisa menolak
        $this->assertSame('Disetujui', $loan->fresh()->status);
    }

    public function test_loan_page_buttons_follow_the_role(): void
    {
        $loan = $this->loan('Disetujui');
        $pending = $this->loan('Menunggu Approval');

        $this->actingAs($this->kasir);
        $this->get("/pinjaman/{$loan->id}")->assertOk()->assertSee('Cairkan Dana')->assertDontSee('Tolak');
        $this->get("/pinjaman/{$pending->id}")->assertOk()->assertDontSee('Setujui')->assertDontSee('Cairkan Dana');

        $this->actingAs($this->admin);
        $this->get("/pinjaman/{$pending->id}")->assertOk()->assertSee('Setujui')->assertSee('Tolak');
    }
}
