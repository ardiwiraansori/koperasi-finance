<?php

namespace Tests\Feature;

use App\Models\JournalEntry;
use App\Models\Loan;
use App\Models\Member;
use App\Models\Setting;
use App\Models\TransactionType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolesTest extends TestCase
{
    use RefreshDatabase;

    private function as(string $role): User
    {
        $user = User::firstOrCreate(['email' => "$role@x.test"], ['role' => $role, 'name' => ucfirst($role), 'password' => 'x']);
        $this->actingAs($user);
        return $user;
    }

    private function cash(float $amount = 10000000): void
    {
        $j = JournalEntry::create(['journal_no' => 'JU-OPEN', 'journal_date' => '2020-01-01', 'description' => 'Saldo awal', 'source' => 'AUTO JOURNAL', 'status' => 'Posted']);
        $j->lines()->create(['account_code' => '10101', 'account_name' => 'Kas Teller', 'debit' => $amount, 'credit' => 0]);
        $j->lines()->create(['account_code' => '39101', 'account_name' => 'Ekuitas Saldo Awal', 'debit' => 0, 'credit' => $amount]);
    }

    private const ALL = ['/dashboard', '/anggota', '/simpanan', '/simpanan/rekening-koran', '/pinjaman', '/cari'];
    private const INPUT = ['/kasir', '/anggota/tambah', '/simpanan/setoran', '/simpanan/penarikan', '/pinjaman/ajukan'];
    private const ACCOUNTING = ['/akuntansi', '/akuntansi/buku-besar', '/akuntansi/neraca-saldo', '/akuntansi/jenis-transaksi', '/laporan', '/laporan/neraca', '/laporan/laba-rugi', '/shu'];
    private const OPENING = ['/simpanan/saldo-awal', '/pinjaman/saldo-awal'];

    private function expect(array $urls, int $status): void
    {
        foreach ($urls as $url) {
            $this->get($url)->assertStatus($status, "GET $url seharusnya $status");
        }
    }

    public function test_kasir_can_only_input_and_view_not_account_decide_or_configure(): void
    {
        $this->as('kasir');
        $this->expect(array_merge(self::ALL, self::INPUT), 200);
        $this->expect(array_merge(self::ACCOUNTING, self::OPENING, ['/akuntansi/jurnal-manual', '/akuntansi/export', '/approval', '/pengaturan', '/anggota/export']), 403);
    }

    public function test_akuntan_sees_accounting_and_opening_but_cannot_input_or_decide(): void
    {
        Setting::put('has_accountant', '1');
        $this->as('akuntan');
        $this->expect(array_merge(self::ALL, self::ACCOUNTING, self::OPENING, ['/akuntansi/jurnal-manual', '/akuntansi/export', '/anggota/export']), 200);
        $this->expect(array_merge(self::INPUT, ['/approval', '/pengaturan']), 403);
    }

    public function test_admin_can_open_everything_but_manual_journal_only_with_an_accountant(): void
    {
        $this->as('admin');
        $this->expect(array_merge(self::ALL, self::INPUT, self::ACCOUNTING, self::OPENING, ['/approval', '/pengaturan', '/anggota/export']), 200);

        $this->get('/akuntansi/jurnal-manual')->assertForbidden();                      // mode tanpa akuntan (bawaan)
        Setting::put('has_accountant', '1');
        $this->get('/akuntansi/jurnal-manual')->assertOk();
    }

    public function test_write_actions_are_blocked_on_the_server_not_just_hidden(): void
    {
        $this->cash();
        $this->as('kasir');
        $m = Member::create(['member_no' => 'AG000001', 'name' => 'Budi', 'branch' => 'Pusat', 'joined_at' => '2020-01-01', 'status' => 'Aktif']);
        $loan = Loan::create(['member_id' => $m->id, 'loan_no' => 'PIN-1', 'product' => 'Pinjaman Reguler', 'principal' => 1000000, 'interest_rate' => 12, 'term_months' => 12,
            'installment' => 88849, 'outstanding' => 0, 'status' => 'Menunggu Approval', 'applied_at' => today()]);

        $this->post("/pinjaman/{$loan->id}/setujui")->assertForbidden();
        $this->post("/pinjaman/{$loan->id}/cairkan")->assertForbidden();
        $this->post("/pinjaman/{$loan->id}/tolak", ['note' => 'x'])->assertForbidden();
        $this->assertSame('Menunggu Approval', $loan->fresh()->status);

        $this->post('/akuntansi/jenis-transaksi', ['category' => 'Pengeluaran', 'name' => 'Baru', 'account_code' => '51101'])->assertForbidden();
        $this->post('/simpanan/saldo-awal', ['member_id' => $m->id, 'tanggal' => today()->toDateString(), 'saldo' => ['Wajib' => '1.000']])->assertForbidden();
        $this->post('/pengaturan/pengguna', ['name' => 'X', 'email' => 'x@x.test', 'password' => 'rahasia123', 'role' => 'admin'])->assertForbidden();
        $this->assertSame(1, User::count());

        // Saldo awal kas lewat Kasir juga hanya admin/akuntan
        $this->post('/kasir', ['category' => 'Saldo Awal', 'amount' => '1.000', 'description' => 'x'])->assertSessionHasErrors('category');
    }

    public function test_akuntan_cannot_enter_cash_transactions_or_decide_loans(): void
    {
        Setting::put('has_accountant', '1');
        $this->as('akuntan');
        $this->post('/kasir', ['category' => 'Penerimaan', 'jenis_id' => TransactionType::first()->id, 'amount' => '1.000', 'description' => 'x'])->assertForbidden();
        $this->post('/simpanan/setoran', ['saving_id' => 1, 'amount' => '1000'])->assertForbidden();
    }

    public function test_kasir_works_end_to_end_without_any_accounting_knowledge(): void
    {
        $this->cash();
        $this->as('kasir');

        $this->post('/anggota', ['name' => 'Siti', 'branch' => 'Pusat', 'status' => 'Aktif', 'joined_at' => '2026-01-01', 'simpanan_pokok' => '100.000'])->assertSessionHasNoErrors();
        $member = Member::where('name', 'Siti')->firstOrFail();
        $wajib = $member->savings()->where('type', 'Wajib')->first();

        $this->post('/simpanan/setoran', ['saving_id' => $wajib->id, 'amount' => '50.000'])->assertSessionHasNoErrors();
        $this->post('/kasir', ['category' => 'Penerimaan', 'jenis_id' => TransactionType::where('name', 'Denda keterlambatan')->value('id'), 'amount' => '5.000', 'description' => 'Denda'])->assertSessionHasNoErrors();
        $this->post('/kasir', ['category' => 'Pengeluaran', 'jenis_id' => TransactionType::where('name', 'Gaji dan honor')->value('id'), 'amount' => '20.000', 'description' => 'Honor'])->assertSessionHasNoErrors();
        $this->post('/pinjaman', ['member_id' => $member->id, 'product' => 'Pinjaman Reguler', 'principal' => '1.000.000', 'term_months' => 12])->assertSessionHasNoErrors();

        // Jurnalnya tetap dibuat otomatis dan seimbang; kasir tidak pernah memilih akun.
        $this->assertSame(0, JournalEntry::get()->filter(fn ($j) => round($j->lines->sum('debit'), 2) !== round($j->lines->sum('credit'), 2))->count());
        $this->get('/kasir')->assertOk()->assertDontSee('name="account_code"', false)->assertDontSee('Beban Gaji');
    }

    public function test_menu_shows_only_what_each_role_may_use(): void
    {
        $this->as('kasir');
        $page = $this->get('/dashboard')->assertOk()->assertSee('Kasir');
        foreach (['accounting.index', 'reports.index', 'approval.index', 'shu.index', 'settings.index'] as $route) {
            $page->assertDontSee('href="'.route($route).'"', false);
        }
        $page->assertSee('href="'.route('teller.index').'"', false);

        $this->as('admin');
        $page = $this->get('/dashboard')->assertOk();
        foreach (['accounting.index', 'reports.index', 'approval.index', 'shu.index', 'settings.index', 'teller.index'] as $route) {
            $page->assertSee('href="'.route($route).'"', false);
        }

        // tombol aksi ikut disembunyikan untuk kasir
        $this->as('kasir');
        $this->get('/simpanan')->assertDontSee(route('savings.opening'), false)->assertSee(route('savings.deposit'), false);
        $this->get('/anggota')->assertDontSee(route('members.export'), false)->assertSee(route('members.create'), false);
    }

    public function test_without_an_accountant_types_get_an_automatic_account_and_cannot_be_remapped(): void
    {
        $this->as('admin');

        $this->post('/akuntansi/jenis-transaksi', ['category' => 'Pengeluaran', 'name' => 'Sewa kantor'])->assertSessionHasNoErrors();   // tanpa memilih akun
        $this->post('/akuntansi/jenis-transaksi', ['category' => 'Penerimaan', 'name' => 'Jasa titipan', 'account_code' => '41101'])->assertSessionHasNoErrors();
        $this->assertSame('59101', TransactionType::where('name', 'Sewa kantor')->value('account_code'));       // otomatis "lain-lain"
        $this->assertSame('49101', TransactionType::where('name', 'Jasa titipan')->value('account_code'));       // pilihan akun diabaikan

        $type = TransactionType::where('name', 'Gaji dan honor')->firstOrFail();
        $this->put("/akuntansi/jenis-transaksi/{$type->id}", ['name' => 'Gaji pegawai', 'account_code' => '51101', 'active' => '1'])->assertSessionHasNoErrors();
        $this->assertSame(['Gaji pegawai', '51201'], [$type->fresh()->name, $type->fresh()->account_code]);      // nama boleh, akun tetap

        $this->get('/akuntansi/jenis-transaksi')->assertOk()->assertSee('otomatis')->assertDontSee('name="account_code"', false);
        $this->get('/akuntansi')->assertDontSee('＋ Jurnal Manual')->assertDontSee(route('accounting.manual'), false);
    }

    public function test_settings_mode_and_name(): void
    {
        $this->as('admin');
        $this->get('/pengaturan')->assertOk()->assertSee('Tanpa akuntan')->assertSee('Koperasi Maju Bersama');

        $this->put('/pengaturan', ['koperasi_name' => 'Koperasi Sejahtera', 'has_accountant' => '1'])->assertSessionHasNoErrors();
        $this->assertTrue(Setting::hasAccountant());
        $this->get('/dashboard')->assertSee('Koperasi Sejahtera');
        $this->get('/akuntansi')->assertSee(route('accounting.manual'), false);

        $this->put('/pengaturan', ['koperasi_name' => '', 'has_accountant' => '1'])->assertSessionHasErrors('koperasi_name');
        $this->put('/pengaturan', ['koperasi_name' => 'X', 'has_accountant' => '2'])->assertSessionHasErrors('has_accountant');
        $this->assertSame('Koperasi Sejahtera', Setting::cooperativeName());
    }

    public function test_user_management_rules(): void
    {
        $admin = $this->as('admin');

        $this->post('/pengaturan/pengguna', ['name' => 'Kasir Baru', 'email' => 'kasir@x.test', 'password' => 'rahasia123', 'role' => 'kasir'])->assertSessionHasNoErrors();
        $this->post('/pengaturan/pengguna', ['name' => 'Akun', 'email' => 'akuntan@x.test', 'password' => 'rahasia123', 'role' => 'akuntan'])->assertSessionHasErrors('role');   // mode tanpa akuntan
        $this->post('/pengaturan/pengguna', ['name' => 'Dobel', 'email' => 'kasir@x.test', 'password' => 'rahasia123', 'role' => 'kasir'])->assertSessionHasErrors('email');
        $this->post('/pengaturan/pengguna', ['name' => 'Pendek', 'email' => 'p@x.test', 'password' => 'abc', 'role' => 'kasir'])->assertSessionHasErrors('password');
        Setting::put('has_accountant', '1');
        $this->post('/pengaturan/pengguna', ['name' => 'Akun', 'email' => 'akuntan@x.test', 'password' => 'rahasia123', 'role' => 'akuntan'])->assertSessionHasNoErrors();
        $this->assertSame('kasir', User::where('email', 'kasir@x.test')->value('role'));

        $kasir = User::where('email', 'kasir@x.test')->firstOrFail();
        $this->put("/pengaturan/pengguna/{$kasir->id}", ['name' => 'Kasir Baru', 'email' => 'kasir@x.test', 'role' => 'kasir', 'password' => 'sandi-baru-123'])->assertSessionHasNoErrors();
        $this->assertTrue(\Hash::check('sandi-baru-123', $kasir->fresh()->password));
        $this->put("/pengaturan/pengguna/{$kasir->id}", ['name' => 'Kasir Baru', 'email' => 'kasir@x.test', 'role' => 'kasir'])->assertSessionHasNoErrors();
        $this->assertTrue(\Hash::check('sandi-baru-123', $kasir->fresh()->password));      // sandi kosong = tidak berubah

        $this->put("/pengaturan/pengguna/{$admin->id}", ['name' => 'Admin', 'email' => $admin->email, 'role' => 'kasir'])->assertSessionHasErrors('role');   // tidak bisa menurunkan diri sendiri
        $this->assertSame('admin', $admin->fresh()->role);
    }

    public function test_deleting_a_user_kills_their_open_session_and_is_guarded(): void
    {
        // Kasir sungguhan login lewat formulir (sesi terbuka). Setelah akunnya dihapus, permintaan berikutnya dialihkan ke login.
        $leaver = User::create(['role' => 'kasir', 'name' => 'Kasir Keluar', 'email' => 'keluar@x.test', 'password' => 'rahasia123']);
        $this->post('/login', ['email' => 'keluar@x.test', 'password' => 'rahasia123'])->assertRedirect('/dashboard');
        $this->get('/dashboard')->assertOk();

        $leaver->delete();                                     // yang dilakukan tombol Hapus di Pengaturan
        $this->app['auth']->forgetGuards();                    // permintaan berikutnya memuat pengguna dari sesi lagi
        $this->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_user_deletion_rules(): void
    {
        $admin = $this->as('admin');
        $kasir = User::create(['role' => 'kasir', 'name' => 'K', 'email' => 'k@x.test', 'password' => 'x']);

        $this->delete("/pengaturan/pengguna/{$admin->id}")->assertSessionHas('error');               // tidak bisa menghapus diri sendiri
        $this->assertNotNull(User::find($admin->id));

        $second = User::create(['role' => 'admin', 'name' => 'Dua', 'email' => 'dua@x.test', 'password' => 'x']);
        $this->delete("/pengaturan/pengguna/{$second->id}")->assertSessionHas('success');              // admin lain boleh dihapus (masih ada satu)
        $this->delete("/pengaturan/pengguna/{$kasir->id}")->assertSessionHas('success');
        $this->assertSame(1, User::count());

        $this->actingAs(User::create(['role' => 'kasir', 'name' => 'K2', 'email' => 'k2@x.test', 'password' => 'x']));
        $this->delete("/pengaturan/pengguna/{$admin->id}")->assertForbidden();                              // kasir tidak boleh menghapus
        $this->assertNotNull(User::find($admin->id));
        $this->assertNotNull($kasir);
    }

    public function test_cannot_demote_the_last_admin_and_new_accounts_default_to_least_privilege(): void
    {
        $admin = $this->as('admin');
        $second = User::create(['role' => 'admin', 'name' => 'Kedua', 'email' => 'dua@x.test', 'password' => 'x']);

        $this->put("/pengaturan/pengguna/{$second->id}", ['name' => 'Kedua', 'email' => 'dua@x.test', 'role' => 'kasir'])->assertSessionHasNoErrors();   // masih ada admin lain
        $this->assertSame('kasir', $second->fresh()->role);

        $other = User::create(['name' => 'Tanpa Peran', 'email' => 'tp@x.test', 'password' => 'x']);
        $this->assertSame('kasir', $other->fresh()->role);                                // bawaan basis data: hak paling kecil
        $this->assertSame('admin', $admin->fresh()->role);
    }
}
