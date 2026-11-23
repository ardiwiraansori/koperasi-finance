<?php

namespace Tests\Feature;

use App\Models\Loan;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Laporan Tunggakan dan Kolektibilitas: pinjaman berjalan yang lewat jatuh tempo, menurut umur.
class ArrearsReportTest extends TestCase
{
    use RefreshDatabase;

    private int $n = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::create(['role' => 'admin', 'name' => 'Admin', 'email' => 'a@x.test', 'password' => 'x']));
    }

    private function loan(string $name, string $status, float $outstanding, ?int $daysLate, array $attrs = []): Loan
    {
        $this->n++;
        $member = Member::create(['member_no' => sprintf('AG%06d', $this->n), 'name' => $name, 'phone' => "0812000$this->n", 'branch' => 'Pusat', 'joined_at' => '2026-01-01', 'status' => 'Aktif']);

        return Loan::create($attrs + [
            'member_id' => $member->id, 'loan_no' => sprintf('PIN-T%03d', $this->n), 'product' => Loan::PRODUCT, 'principal' => 10000000, 'interest_rate' => 12.5,
            'term_months' => 12, 'installment' => 1000000, 'outstanding' => $outstanding, 'status' => $status, 'applied_at' => today()->subYear(),
            'disbursed_at' => today()->subYear(), 'next_due_at' => $daysLate === null ? null : today()->subDays($daysLate),
        ]);
    }

    private function dataset(): void
    {
        $this->loan('Lancar', 'Aktif', 5000000, -10);          // jatuh tempo 10 hari lagi
        $this->loan('Sepuluh', 'Tunggakan', 2000000, 10);      // 1-30 hari
        $this->loan('Empatlima', 'Tunggakan', 3000000, 45);    // 31-60
        $this->loan('Tujuhpuluh', 'Tunggakan', 1000000, 70);   // 61-90
        $this->loan('Sembilanlima', 'Tunggakan', 4000000, 95); // > 90 (4 jatuh tempo terlewat)
        $this->loan('Lunas', 'Lunas', 0, null);                // tidak ikut
        $this->loan('Ditolak', 'Ditolak', 0, 200);             // tidak ikut walau tanggalnya lampau
        $this->loan('Menunggu', 'Menunggu Approval', 0, 200);  // belum dicairkan, tidak ikut
    }

    public function test_only_running_overdue_loans_are_listed_oldest_first_with_their_age_group(): void
    {
        $this->dataset();
        $res = $this->get('/laporan/tunggakan')->assertOk();
        $rows = $res->viewData('rows');

        $this->assertSame(['Sembilanlima', 'Tujuhpuluh', 'Empatlima', 'Sepuluh'], $rows->pluck('member.name')->all());   // terlama di atas
        $this->assertSame(['Lebih dari 90 hari (NPL)', '61–90 hari', '31–60 hari', '1–30 hari'], $rows->pluck('bucket')->all());
        $this->assertSame([95, 70, 45, 10], $rows->pluck('days')->all());
    }

    public function test_missed_installments_and_amounts_per_loan(): void
    {
        $this->dataset();
        $rows = $this->get('/laporan/tunggakan')->viewData('rows')->keyBy('member.name');

        $this->assertSame(4, $rows['Sembilanlima']['missed']);                 // jatuh tempo hari ke 0, 30, 60, 90 sudah lewat
        $this->assertEquals(4000000, $rows['Sembilanlima']['due']);            // 4 x cicilan 1.000.000
        $this->assertSame(1, $rows['Sepuluh']['missed']);
        $this->assertEquals(1000000, $rows['Sepuluh']['due']);
    }

    public function test_collectibility_groups_and_summary_use_the_running_portfolio(): void
    {
        $this->dataset();
        $res = $this->get('/laporan/tunggakan')->assertOk();
        $groups = collect($res->viewData('groups'))->keyBy('label');
        $s = $res->viewData('summary');

        // Portofolio berjalan = 5 + 2 + 3 + 1 + 4 = 15 juta (5 pinjaman).
        $this->assertEquals(15000000, $s['portfolio']);
        $this->assertEquals([1, 5000000], [$groups['Lancar (tidak menunggak)']['loans'], $groups['Lancar (tidak menunggak)']['outstanding']]);
        $this->assertEquals([1, 4000000, 26.7], [$groups['Lebih dari 90 hari (NPL)']['loans'], $groups['Lebih dari 90 hari (NPL)']['outstanding'], $groups['Lebih dari 90 hari (NPL)']['pct']]);
        $this->assertSame(15000000.0, (float) collect($res->viewData('groups'))->sum('outstanding'));   // kelompok menjumlah ke seluruh portofolio

        $this->assertEquals([4, 4, 10000000, 66.7, 26.7], [$s['loans'], $s['members'], $s['outstanding'], $s['overduePct'], $s['npl']]);
        // 4 + 3 + 2 + 1 jatuh tempo terlewat x cicilan 1.000.000, tetapi "Tujuhpuluh" hanya bersisa pokok 1.000.000 (+ bunga 3 x 10.417), jadi
        // 3.000.000 dibatasi menjadi 1.031.251: 4.000.000 + 1.031.251 + 2.000.000 + 1.000.000.
        $this->assertEquals(8031251, $s['due']);
    }

    public function test_estimated_arrears_never_exceed_what_the_member_owes(): void
    {
        // Sisa pokok 1.000.000, cicilan 1.000.000, terlambat 95 hari (4 jatuh tempo terlewat): 4 x cicilan = 4.000.000, padahal kewajibannya
        // sisa pokok + bunga 4 bulan = 1.000.000 + 4 x 10.417 = 1.041.668.
        $this->loan('Sisakecil', 'Tunggakan', 1000000, 95);
        $row = $this->get('/laporan/tunggakan')->assertOk()->viewData('rows')->first();

        $this->assertSame(4, $row['missed']);
        $this->assertEquals(1041668, $row['due']);
        $this->assertEquals(1041668, $this->get('/laporan/tunggakan')->viewData('summary')['due']);   // total halaman ikut dibatasi
        $this->assertStringContainsString('1041668', $this->get('/laporan/tunggakan?export=1')->streamedContent());

        // Pinjaman dengan sisa besar tidak terpengaruh: 4 x 1.000.000 jauh di bawah kewajiban 20 juta lebih.
        $this->loan('Sisabesar', 'Tunggakan', 20000000, 95);
        $this->assertEquals(4000000, $this->get('/laporan/tunggakan')->viewData('rows')->firstWhere('member.name', 'Sisabesar')['due']);
    }

    public function test_the_page_calls_the_amount_an_estimate(): void
    {
        $this->loan('Sepuluh', 'Tunggakan', 2000000, 10);
        $this->get('/laporan/tunggakan')->assertOk()->assertSeeCollapsed('Perkiraan Tertunggak')->assertSeeCollapsed('pembayaran sebagian (di bawah cicilan) belum dikurangkan');
    }

    public function test_each_age_group_counts_its_own_loans(): void
    {
        $this->dataset();
        $groups = collect($this->get('/laporan/tunggakan')->viewData('groups'))->keyBy('label');

        foreach (['1–30 hari' => 2000000, '31–60 hari' => 3000000, '61–90 hari' => 1000000] as $label => $outstanding) {
            $this->assertEquals([1, $outstanding], [$groups[$label]['loans'], $groups[$label]['outstanding']], $label);
        }
    }

    public function test_page_renders_the_list_and_the_empty_state(): void
    {
        $this->get('/laporan/tunggakan')->assertOk()->assertSeeCollapsed('Tidak ada pinjaman menunggak')->assertSeeCollapsed('Pinjaman menunggak');

        $this->dataset();
        $this->get('/laporan/tunggakan')->assertOk()->assertSee('Sembilanlima')->assertSee('PIN-T005')->assertSee('0812000'.'5')->assertSeeCollapsed('95 hari')
            ->assertSeeCollapsed('4 jatuh tempo terlewat')->assertDontSee('Ditolak')->assertDontSee('Menunggu')->assertSeeCollapsed('Rp 10.000.000');
    }

    public function test_export_lists_overdue_loans_with_phone_and_amounts(): void
    {
        $this->dataset();
        $csv = $this->get('/laporan/tunggakan?export=1')->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8')->streamedContent();

        $this->assertStringContainsString('Umur;"No Pinjaman";"No Anggota";Nama;Telepon', $csv);
        $this->assertStringContainsString('Sembilanlima', $csv);
        $this->assertStringContainsString('08120005', $csv);
        $this->assertStringNotContainsString('Ditolak', $csv);
        $this->assertLessThan(strpos($csv, 'Sepuluh'), strpos($csv, 'Sembilanlima'));      // terlama dulu
    }

    public function test_kasir_can_read_it_and_the_report_hub_links_to_it(): void
    {
        $this->actingAs(User::create(['role' => 'kasir', 'name' => 'Kasir', 'email' => 'k@x.test', 'password' => 'x']));

        $this->get('/laporan/tunggakan')->assertOk();
        $this->get('/laporan')->assertOk()->assertSee(route('reports.arrears'), false);
    }

    public function test_member_accounts_cannot_open_it(): void
    {
        $this->actingAs(User::create(['role' => 'anggota', 'name' => 'Ani', 'email' => 'ani@anggota.local', 'password' => 'x']));
        $this->get('/laporan/tunggakan')->assertRedirect('/portal');
    }
}
