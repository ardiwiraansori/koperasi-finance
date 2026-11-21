<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Kasir boleh membaca laporan keuangan (Neraca, Laba Rugi, Arus Kas) tetapi tidak halaman akuntansi dan SHU.
class ReportAccessTest extends TestCase
{
    use RefreshDatabase;

    private function as(string $role): void
    {
        $this->actingAs(User::firstOrCreate(['email' => "$role@x.test"], ['role' => $role, 'name' => ucfirst($role), 'password' => 'x']));
    }

    public function test_kasir_reads_the_three_financial_statements_and_exports_them(): void
    {
        $this->as('kasir');
        $y = now()->year;

        $this->get('/laporan')->assertOk()->assertSee('Neraca')->assertSee('Laba Rugi')->assertSee('Arus Kas');
        $this->get('/laporan/neraca')->assertOk();
        $this->get("/laporan/laba-rugi?dari=$y-01-01&sampai=$y-12-31")->assertOk();
        $this->get("/laporan/arus-kas?dari=$y-01-01&sampai=$y-12-31")->assertOk();
        $this->get('/laporan/arus-kas?export=1')->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_report_hub_hides_cards_the_kasir_cannot_open(): void
    {
        $this->as('kasir');
        $hub = $this->get('/laporan')->assertOk();

        foreach (['accounting.ledger', 'accounting.trial', 'accounting.index', 'shu.index'] as $route) {
            $hub->assertDontSee('href="'.route($route).'"', false);
        }
        $hub->assertDontSee('Buku Besar')->assertDontSee('Jurnal Umum')->assertSee(route('loans.index'), false)->assertSee(route('savings.statement'), false);

        $this->as('admin');
        $admin = $this->get('/laporan')->assertOk();
        foreach (['accounting.ledger', 'accounting.trial', 'accounting.index', 'shu.index'] as $route) {
            $admin->assertSee('href="'.route($route).'"', false);
        }
    }

    public function test_kasir_still_cannot_open_accounting_or_shu(): void
    {
        $this->as('kasir');
        foreach (['/akuntansi', '/akuntansi/buku-besar', '/akuntansi/neraca-saldo', '/akuntansi/export', '/shu'] as $url) {
            $this->get($url)->assertForbidden();
        }
    }
}
