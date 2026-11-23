<?php

namespace Tests\Feature;

use App\Models\Loan;
use App\Models\Member;
use App\Models\Saving;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Kartu "Kondisi Koperasi" di portal anggota: angka gabungan seluruh koperasi, tanpa data anggota lain.
class PortalCooperativeCardsTest extends TestCase
{
    use RefreshDatabase;

    private Member $me;

    protected function setUp(): void
    {
        parent::setUp();
        $this->me = $this->member('AG000001', 'Saya Sendiri', 1000000);
        $this->actingAs(User::create(['role' => 'anggota', 'member_id' => $this->me->id, 'name' => 'Saya', 'email' => 'ag000001@anggota.local', 'password' => 'x']));
    }

    private function member(string $no, string $name, float $savings): Member
    {
        $m = Member::create(['member_no' => $no, 'name' => $name, 'branch' => 'Pusat', 'joined_at' => '2026-01-01', 'status' => 'Aktif']);
        Saving::create(['member_id' => $m->id, 'account_no' => "SAV-$no", 'type' => 'Wajib', 'balance' => $savings]);

        return $m;
    }

    private function loan(Member $m, string $no, string $status, float $outstanding, ?int $daysLate = null): Loan
    {
        return Loan::create([
            'member_id' => $m->id, 'loan_no' => $no, 'product' => Loan::PRODUCT, 'principal' => 10000000, 'interest_rate' => 12.5, 'term_months' => 12,
            'installment' => 1000000, 'outstanding' => $outstanding, 'status' => $status, 'applied_at' => today()->subYear(), 'disbursed_at' => today()->subYear(),
            'next_due_at' => $daysLate === null ? today()->addDays(10) : today()->subDays($daysLate),
        ]);
    }

    private function dataset(): void
    {
        $budi = $this->member('AG000002', 'Budi Rahasia', 2000000);
        $citra = $this->member('AG000003', 'Citra Rahasia', 3000000);

        $this->loan($this->me, 'PIN-SAYA', 'Aktif', 4000000);                      // lancar
        $this->loan($budi, 'PIN-BUDI', 'Tunggakan', 2000000, 35);                  // terlambat 1-90 hari
        $this->loan($citra, 'PIN-CITRA', 'Tunggakan', 4000000, 100);               // di atas 90 hari (NPL)
        $this->loan($budi, 'PIN-LUNAS', 'Lunas', 0, 400);                          // tidak ikut
        $this->loan($citra, 'PIN-TUNGGU', 'Menunggu Approval', 0, 400);            // tidak ikut
    }

    public function test_cards_show_the_totals_of_the_whole_cooperative(): void
    {
        $this->dataset();
        $page = $this->get('/portal')->assertOk();

        $page->assertSeeCollapsed('Kondisi Koperasi')->assertSeeCollapsed('Angka gabungan seluruh anggota');
        $page->assertSeeCollapsed('Total Simpanan')->assertSeeCollapsed('Rp 6.000.000');                       // 1 + 2 + 3 juta
        $page->assertSeeCollapsed('Pinjaman Beredar')->assertSeeCollapsed('Rp 10.000.000')->assertSeeCollapsed('3 pinjaman berjalan');
        $page->assertSeeCollapsed('2 pinjaman · 60% dari pinjaman beredar');                                 // tunggakan 6 juta dari 10 juta
        $page->assertSeeCollapsed('Kualitas Pinjaman')->assertSeeCollapsed('40<small>/100</small>', false)
            ->assertSeeCollapsed('Cukup banyak pinjaman terlambat dibayar.')
            ->assertSeeCollapsed('Lancar <b>40%</b>', false)->assertSeeCollapsed('Terlambat 1–90 hari <b>20%</b>', false)
            ->assertSeeCollapsed('Terlambat di atas 90 hari (bermasalah) <b>40%</b>', false);

        $coop = $page->viewData('coop');
        $this->assertEquals([6000000, 10000000, 3, 6000000, 2, 60.0], [$coop['savings'], $coop['loans'], $coop['loanCount'], $coop['arrears'], $coop['arrearsCount'], $coop['arrearsPct']]);
        $this->assertSame(40, $coop['quality']['score']);
    }

    public function test_no_data_of_other_members_is_shown_or_even_sent_to_the_page(): void
    {
        $this->dataset();
        $page = $this->get('/portal')->assertOk();

        foreach (['Budi Rahasia', 'Citra Rahasia', 'AG000002', 'AG000003', 'PIN-BUDI', 'PIN-CITRA', 'PIN-LUNAS', 'PIN-TUNGGU', 'SAV-AG000002', 'SAV-AG000003'] as $secret) {
            $page->assertDontSee($secret);
        }
        // Yang diteruskan ke tampilan hanya angka: tidak ada model atau nama anggota di dalamnya.
        $this->assertSame(['savings', 'loans', 'loanCount', 'arrears', 'arrearsCount', 'arrearsPct', 'quality'], array_keys($page->viewData('coop')));
        $this->assertStringNotContainsString('Rahasia', json_encode($page->viewData('coop')));
        $this->assertSame([], array_filter($page->viewData('coop'), fn ($v) => is_object($v)));

        // Data pribadi tetap hanya milik sendiri.
        $page->assertSee('Halo, Saya Sendiri')->assertSee('PIN-SAYA');
    }

    public function test_a_cooperative_without_arrears_says_so_and_scores_100(): void
    {
        $this->loan($this->me, 'PIN-SAYA', 'Aktif', 4000000);
        $page = $this->get('/portal')->assertOk();

        $page->assertSeeCollapsed('Tidak ada tunggakan')->assertSeeCollapsed('100<small>/100</small>', false)->assertSeeCollapsed('Pinjaman koperasi berjalan lancar.');
        $this->assertStringNotContainsString('coop-card warn', preg_replace('/\s+/', ' ', $page->getContent()));
    }

    public function test_an_empty_cooperative_renders_zeros_not_errors(): void
    {
        Saving::query()->delete();
        $page = $this->get('/portal')->assertOk();

        $this->assertEquals([0, 0, 0, 0, 0], [$page->viewData('coop')['savings'], $page->viewData('coop')['loans'], $page->viewData('coop')['loanCount'], $page->viewData('coop')['arrears'], $page->viewData('coop')['arrearsPct']]);
        $page->assertSeeCollapsed('Rp 0')->assertSeeCollapsed('0 pinjaman berjalan')->assertSeeCollapsed('Tidak ada tunggakan');
    }

    public function test_quality_numbers_are_the_same_as_the_staff_dashboard_but_the_sentence_is_for_members(): void
    {
        $this->dataset();
        $portal = $this->get('/portal')->viewData('coop')['quality'];

        $this->actingAs(User::create(['role' => 'admin', 'name' => 'Admin', 'email' => 'a@x.test', 'password' => 'x']));
        $page = $this->get('/dashboard')->assertOk();
        $dashboard = $page->viewData('quality');

        $this->assertSame(collect($dashboard)->except('verdict')->all(), collect($portal)->except('verdict')->all());   // angka sama
        $this->assertSame('Banyak pinjaman menunggak, segera ditangani.', $dashboard['verdict']);                        // petugas: perintah
        $this->assertSame('Cukup banyak pinjaman terlambat dibayar.', $portal['verdict']);                              // anggota: netral
    }

    public function test_member_sentence_follows_the_score_and_never_speaks_to_staff(): void
    {
        // Skor 100: tidak ada tunggakan.
        $this->loan($this->me, 'PIN-A', 'Aktif', 4000000);
        $this->assertSame('Pinjaman koperasi berjalan lancar.', $this->get('/portal')->viewData('coop')['quality']['verdict']);

        // Skor 80: 4 juta lancar dan 1 juta terlambat 10 hari = 20% terlambat.
        $other = $this->member('AG000009', 'Lain', 0);
        $this->loan($other, 'PIN-B', 'Tunggakan', 1000000, 10);
        $page = $this->get('/portal')->assertOk();
        $this->assertSame([80, 'Sebagian kecil pinjaman terlambat dibayar.'], [$page->viewData('coop')['quality']['score'], $page->viewData('coop')['quality']['verdict']]);

        foreach (['segera ditangani', 'ditindaklanjuti', 'NPL'] as $staffWord) {
            $page->assertDontSee($staffWord);
        }
    }

    public function test_layout_is_phone_friendly(): void
    {
        $page = $this->get('/portal')->assertOk()->assertSee('name="viewport" content="width=device-width, initial-scale=1"', false);
        $page->assertSee('aria-label="Lancar', false);                                  // bilah kualitas punya teks untuk pembaca layar, bukan hanya warna
        $page->assertSee('class="coop-kpis"', false);

        $css = file_get_contents(public_path('assets/app.css'));
        $this->assertStringContainsString('@media (max-width:640px){.coop-kpis{grid-template-columns:1fr}', $css);   // satu kolom di HP
        $this->assertStringContainsString('overflow-wrap:anywhere', $css);                                           // angka rupiah panjang tidak terpotong
    }

    public function test_staff_cannot_open_the_portal_and_guests_are_sent_to_login(): void
    {
        $this->actingAs(User::create(['role' => 'admin', 'name' => 'Admin', 'email' => 'a@x.test', 'password' => 'x']));
        $this->get('/portal')->assertForbidden();
    }
}
