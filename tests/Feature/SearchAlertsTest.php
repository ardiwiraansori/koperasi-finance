<?php

namespace Tests\Feature;

use App\Models\JournalEntry;
use App\Models\Loan;
use App\Models\Member;
use App\Models\Transaction;
use App\Models\User;
use App\Support\Alerts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SearchAlertsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Member $budi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::create(['role' => 'admin', 'name' => 'Admin', 'email' => 'a@x.test', 'password' => 'x']);
        $this->actingAs($this->admin);
        $this->budi = Member::create(['member_no' => 'AG000128', 'name' => 'Budi Santoso', 'phone' => '081234567801', 'branch' => 'Pusat', 'joined_at' => '2020-01-01', 'status' => 'Aktif']);
        $this->budi->savings()->create(['account_no' => 'SAV-0128-2', 'type' => 'Wajib', 'balance' => 500000]);
        Member::create(['member_no' => 'AG000129', 'name' => 'Rina Aprillia', 'branch' => 'Pusat', 'joined_at' => '2020-01-01', 'status' => 'Aktif']);
    }

    private function loan(string $status, array $attrs = []): Loan
    {
        return Loan::create($attrs + ['member_id' => $this->budi->id, 'loan_no' => 'PIN-'.uniqid(), 'product' => 'Pinjaman Reguler', 'principal' => 1000000, 'interest_rate' => 12,
            'term_months' => 12, 'installment' => 88849, 'outstanding' => 0, 'status' => $status, 'applied_at' => today()]);
    }

    public function test_search_finds_members_accounts_loans_and_transactions(): void
    {
        $loan = $this->loan('Aktif', ['loan_no' => 'PIN-2026-000777', 'outstanding' => 900000, 'next_due_at' => today()->addDays(20)]);
        Transaction::create(['trx_no' => 'TRX-XYZ1', 'member_id' => $this->budi->id, 'category' => 'Penerimaan', 'direction' => 'in', 'amount' => 5000, 'description' => 'Denda telat bayar', 'trx_date' => now(), 'status' => 'Posted']);

        $this->get('/cari?q=budi')->assertOk()->assertSee('Budi Santoso')->assertSee('SAV-0128-2')->assertSee('PIN-2026-000777')->assertDontSee('Rina Aprillia');
        $this->get('/cari?q=081234')->assertSee('Budi Santoso');                                   // telepon
        $this->get('/cari?q=AG000129')->assertSee('Rina Aprillia')->assertDontSee('Budi Santoso');  // nomor anggota
        $this->get('/cari?q=SAV-0128')->assertSee('SAV-0128-2');                                    // rekening
        $this->get('/cari?q=000777')->assertSee('PIN-2026-000777');                                 // pinjaman
        $this->get('/cari?q=denda')->assertSee('TRX-XYZ1')->assertSee('Denda telat bayar');         // transaksi
        $this->get('/cari?q=zzzzzz')->assertOk()->assertSee('Tidak ada hasil');
    }

    public function test_search_finds_a_member_by_nik(): void
    {
        $this->budi->update(['nik' => '3201234567890001']);

        $this->get('/cari?q=3201234567890001')->assertOk()->assertSee('Budi Santoso')->assertDontSee('Rina Aprillia');   // NIK lengkap
        $this->get('/cari?q=7890001')->assertSee('Budi Santoso');                                                            // sebagian NIK
        $this->get('/cari?q=3299999999999999')->assertSee('Tidak ada hasil');
    }

    public function test_search_requires_two_characters_and_does_not_treat_wildcards_as_match_all(): void
    {
        $this->get('/cari')->assertOk()->assertSee('Mulai mengetik');
        $this->get('/cari?q=b')->assertOk()->assertSee('minimal 2 karakter')->assertDontSee('Budi Santoso');
        $this->get('/cari?q=%25%25')->assertOk()->assertSee('Tidak ada hasil')->assertDontSee('Budi Santoso');   // "%%" bukan "semua"
        $this->get('/cari?q='.str_repeat('a', 61))->assertSessionHasErrors('q');
    }

    public function test_search_does_not_list_non_cash_opening_entries(): void
    {
        Transaction::create(['trx_no' => 'TRX-OPEN', 'member_id' => $this->budi->id, 'category' => 'Saldo Awal Simpanan', 'direction' => 'in', 'amount' => 500000,
            'description' => 'Saldo awal simpanan Wajib', 'trx_date' => now(), 'status' => 'Posted']);
        $this->get('/cari?q=saldo awal')->assertOk()->assertDontSee('TRX-OPEN');
    }

    public function test_alerts_are_computed_from_data_and_filtered_by_role(): void
    {
        $this->loan('Menunggu Approval');
        $this->loan('Disetujui');
        $this->loan('Aktif', ['outstanding' => 500000, 'disbursed_at' => today()->subMonths(2), 'next_due_at' => today()->subDays(12)]);
        $this->loan('Aktif', ['outstanding' => 500000, 'disbursed_at' => today()->subMonths(2), 'next_due_at' => today()->addDays(3)]);

        $texts = collect(Alerts::for($this->admin))->pluck('text')->implode(' | ');
        $this->assertStringContainsString('1 pengajuan pinjaman menunggu persetujuan', $texts);
        $this->assertStringContainsString('1 pinjaman disetujui, siap dicairkan', $texts);
        $this->assertStringContainsString('1 pinjaman menunggak (terlama 12 hari)', $texts);
        $this->assertStringContainsString('1 angsuran jatuh tempo dalam 7 hari', $texts);

        $kasir = User::create(['role' => 'kasir', 'name' => 'Kasir', 'email' => 'k@x.test', 'password' => 'x']);
        $kasirTexts = collect(Alerts::for($kasir))->pluck('text')->implode(' | ');
        $this->assertStringNotContainsString('persetujuan', $kasirTexts);                      // keputusan bukan urusan kasir
        $this->assertStringContainsString('menunggak', $kasirTexts);
    }

    public function test_bell_shows_real_count_and_panel_not_a_fake_dot(): void
    {
        $this->loan('Menunggu Approval');
        $page = $this->get('/dashboard')->assertOk();
        $page->assertSee('notify-badge', false)->assertSee('data-notify-panel', false)->assertSee('1 pengajuan pinjaman menunggu persetujuan');
        $page->assertDontSee('<i></i>', false);                                                  // titik merah palsu lama

        Loan::query()->delete();
        $j = JournalEntry::create(['journal_no' => 'JU-O', 'journal_date' => '2020-01-01', 'description' => 'x', 'source' => 'AUTO JOURNAL', 'status' => 'Posted']);
        $j->lines()->create(['account_code' => '10101', 'account_name' => 'Kas Teller', 'debit' => 1000000, 'credit' => 0]);
        $j->lines()->create(['account_code' => '39101', 'account_name' => 'Ekuitas Saldo Awal', 'debit' => 0, 'credit' => 1000000]);   // saldo awal kas sudah ada
        $this->get('/dashboard')->assertDontSee('notify-badge', false)->assertSee('Tidak ada pemberitahuan baru');
    }

    public function test_missing_opening_cash_balance_is_flagged_until_recorded(): void
    {
        $this->assertStringContainsString('Saldo awal kas belum dicatat', collect(Alerts::for($this->admin))->pluck('text')->implode('|'));

        $j = JournalEntry::create(['journal_no' => 'JU-O', 'journal_date' => '2020-01-01', 'description' => 'x', 'source' => 'AUTO JOURNAL', 'status' => 'Posted']);
        $j->lines()->create(['account_code' => '10101', 'account_name' => 'Kas Teller', 'debit' => 1000000, 'credit' => 0]);
        $j->lines()->create(['account_code' => '39101', 'account_name' => 'Ekuitas Saldo Awal', 'debit' => 0, 'credit' => 1000000]);
        $this->assertStringNotContainsString('Saldo awal kas belum dicatat', collect(Alerts::for($this->admin))->pluck('text')->implode('|'));
    }

    public function test_member_profile_shows_real_risk_and_account_count_not_demo_numbers(): void
    {
        $page = $this->get("/anggota/{$this->budi->id}")->assertOk()->assertSee("1 rekening simpanan")->assertSee("Rendah")->assertDontSee("Skor demo")->assertDontSee("86 / 100");

        $this->loan("Aktif", ["outstanding" => 500000, "disbursed_at" => today()->subMonths(2), "next_due_at" => today()->subDays(45)]);
        $this->get("/anggota/{$this->budi->id}")->assertSee("Tinggi")->assertSee("Pinjaman terlambat 45 hari");
        $this->assertNotNull($page);
    }

    public function test_loan_status_counts_are_queried_once_per_request(): void
    {
        $this->loan('Menunggu Approval');
        $this->loan('Aktif', ['outstanding' => 500000, 'disbursed_at' => today()->subMonth(), 'next_due_at' => today()->addDays(3)]);

        DB::enableQueryLog();
        $page = $this->get('/dashboard')->assertOk();
        $sql = collect(DB::getQueryLog())->pluck('query');
        DB::disableQueryLog();

        $this->assertSame(1, $sql->filter(fn ($q) => str_contains($q, 'group by "status"'))->count());     // satu hitungan status untuk menu, lonceng, dan Dashboard
        $this->assertSame(0, $sql->filter(fn ($q) => str_contains($q, 'count(*)') && str_contains($q, '"loans"') && ! str_contains($q, 'group by'))->count());
        $page->assertSee('<b class="nav-count" aria-label="1 menunggu">1</b>', false)->assertSee('1 pengajuan pinjaman menunggu persetujuan')->assertSee('1 angsuran jatuh tempo dalam 7 hari');
    }

    public function test_topbar_links_to_search_and_pill_shows_current_month(): void
    {
        $this->get('/dashboard')->assertSee(route('search'), false)->assertSee(now()->translatedFormat('F Y'))->assertDontSee('September 2026 ▾');
    }
}
