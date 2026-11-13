<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\Saving;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberPortalTest extends TestCase
{
    use RefreshDatabase;

    private function member(string $no, string $name, string $status = 'Aktif'): Member
    {
        $m = Member::create(['member_no' => $no, 'name' => $name, 'branch' => 'Pusat', 'joined_at' => '2026-01-01', 'status' => $status]);
        Saving::create(['member_id' => $m->id, 'account_no' => "SAV-$no", 'type' => 'Wajib', 'balance' => 1234500]);
        Transaction::create(['trx_no' => "T-$no", 'member_id' => $m->id, 'category' => 'Simpanan', 'direction' => 'in', 'amount' => 1234500, 'description' => "Setoran rahasia $name", 'trx_date' => now(), 'status' => 'Selesai']);
        return $m;
    }

    /** Petugas membuat akun; mengembalikan kata sandi yang ditampilkan sekali. */
    private function createAccount(Member $m): string
    {
        $this->actingAs(User::create(['role' => 'kasir', 'name' => 'Kasir', 'email' => 'k'.uniqid().'@x.test', 'password' => 'x']));
        $this->post("/anggota/{$m->id}/akun")->assertRedirect("/anggota/{$m->id}");
        $password = session('portal_login.password');
        $this->assertSame(8, strlen($password));
        auth()->logout();
        $this->app['auth']->forgetGuards();
        return $password;
    }

    public function test_member_logs_in_with_member_number_and_sees_only_own_data(): void
    {
        $siti = $this->member('AG000128', 'Siti');
        $this->member('AG000129', 'Budi');
        $password = $this->createAccount($siti);

        $this->post('/login', ['email' => ' ag000128 ', 'password' => $password])->assertSessionHasNoErrors();
        $this->assertAuthenticated();
        $this->get('/portal')->assertOk()->assertSee('Halo, Siti')->assertSee('Rp 1.234.500')->assertSee('Setoran rahasia Siti')
            ->assertDontSee('Budi')->assertDontSee('Setoran rahasia Budi');
    }

    public function test_wrong_password_and_plain_number_without_account_fail(): void
    {
        $siti = $this->member('AG000128', 'Siti');
        $this->createAccount($siti);
        $this->post('/login', ['email' => 'AG000128', 'password' => 'salah-salah'])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => 'AG000999', 'password' => 'apa-saja'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_member_account_cannot_reach_any_staff_page(): void
    {
        $siti = $this->member('AG000128', 'Siti');
        $budi = $this->member('AG000129', 'Budi');
        $this->actingAs(User::where('id', '>', 0)->first() ?? User::create(['role' => 'kasir', 'name' => 'K', 'email' => 'k@x.test', 'password' => 'x']));
        $password = $this->createAccount($siti);
        $this->post('/login', ['email' => 'AG000128', 'password' => $password]);

        foreach (['/dashboard', '/', '/anggota', "/anggota/{$budi->id}", "/anggota/{$budi->id}/formulir", '/simpanan', '/pinjaman', '/kasir', '/akuntansi', '/laporan', '/shu', '/pengaturan', '/cari', '/anggota/tambah'] as $url) {
            $this->get($url)->assertRedirect('/portal');
        }
        $this->post('/kasir', [])->assertRedirect('/portal');
        $this->post("/anggota/{$budi->id}/akun")->assertRedirect('/portal');
        $this->put("/anggota/{$budi->id}", ['name' => 'Diretas'])->assertRedirect('/portal');
        $this->assertSame('Budi', $budi->fresh()->name);
    }

    public function test_staff_and_guests_cannot_use_the_portal(): void
    {
        $this->get('/portal')->assertRedirect('/login');
        $this->actingAs(User::create(['role' => 'admin', 'name' => 'Admin', 'email' => 'a@x.test', 'password' => 'x']));
        $this->get('/portal')->assertForbidden();
    }

    public function test_inactive_member_is_blocked_and_logged_out(): void
    {
        $siti = $this->member('AG000128', 'Siti');
        $password = $this->createAccount($siti);
        $this->post('/login', ['email' => 'AG000128', 'password' => $password]);
        $this->get('/portal')->assertOk();

        $siti->update(['status' => 'Nonaktif']);
        auth()->user()->unsetRelation('member');   // di tes, objek user dipakai ulang antar request; di produksi dimuat ulang
        $this->get('/portal')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_account_creation_rules(): void
    {
        $siti = $this->member('AG000128', 'Siti');
        $off = $this->member('AG000130', 'Nonaktif', 'Nonaktif');

        $this->actingAs(User::create(['role' => 'akuntan', 'name' => 'Akt', 'email' => 'ak@x.test', 'password' => 'x']));
        $this->post("/anggota/{$siti->id}/akun")->assertForbidden();

        $this->actingAs(User::create(['role' => 'kasir', 'name' => 'Kasir', 'email' => 'k@x.test', 'password' => 'x']));
        $this->post("/anggota/{$off->id}/akun")->assertStatus(422);
        $this->assertSame(0, User::where('role', 'anggota')->count());

        $this->post("/anggota/{$siti->id}/akun");
        $first = session('portal_login.password');
        $this->post("/anggota/{$siti->id}/akun");   // atur ulang: tetap satu akun, sandi baru
        $this->assertSame(1, User::where('role', 'anggota')->count());
        $this->assertNotSame($first, session('portal_login.password'));
        $this->get("/anggota/{$siti->id}")->assertSee('Atur Ulang Kata Sandi Portal');
    }

    public function test_member_accounts_are_hidden_from_the_staff_user_list(): void
    {
        $siti = $this->member('AG000128', 'Siti');
        $this->createAccount($siti);
        $this->actingAs(User::create(['role' => 'admin', 'name' => 'Admin', 'email' => 'a@x.test', 'password' => 'x']));
        $this->get('/pengaturan')->assertOk()->assertDontSee('ag000128@anggota.local');
    }
}
