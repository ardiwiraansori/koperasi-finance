<?php

namespace Tests\Feature;

use App\Models\JournalEntry;
use App\Models\Loan;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Konfirmasi dan alasan memakai dialog di dalam halaman (data-dialog), bukan confirm()/prompt() bawaan browser.
// Perilaku dialog sendiri ada di app.js dan tidak bisa dijalankan di tes server; yang dijaga di sini adalah penandanya.
class DialogMarkupTest extends TestCase
{
    use RefreshDatabase;

    private Member $member;
    private Loan $approved;
    private Loan $pending;

    protected function setUp(): void
    {
        parent::setUp();
        $this->member = Member::create(['member_no' => 'AG000001', 'name' => 'Budi', 'branch' => 'Pusat', 'joined_at' => '2026-01-01', 'status' => 'Aktif']);
        $j = JournalEntry::create(['journal_no' => 'JU-OPEN', 'journal_date' => '2026-01-01', 'description' => 'Saldo awal', 'source' => 'AUTO JOURNAL', 'status' => 'Posted']);
        $j->lines()->create(['account_code' => '10101', 'account_name' => 'Kas Teller', 'debit' => 20000000, 'credit' => 0]);
        $j->lines()->create(['account_code' => '39101', 'account_name' => 'Ekuitas Saldo Awal', 'debit' => 0, 'credit' => 20000000]);
        $base = ['member_id' => $this->member->id, 'product' => Loan::PRODUCT, 'principal' => 5000000, 'interest_rate' => 12.5, 'term_months' => 12, 'installment' => 1, 'outstanding' => 0, 'applied_at' => today()];
        $this->approved = Loan::create($base + ['loan_no' => 'PIN-SETUJU', 'status' => 'Disetujui']);
        $this->pending = Loan::create($base + ['loan_no' => 'PIN-TUNGGU', 'status' => 'Menunggu Approval']);
    }

    private function user(string $role): void
    {
        $this->actingAs(User::firstOrCreate(['email' => "$role@x.test"], ['role' => $role, 'name' => ucfirst($role), 'password' => 'x']));
    }

    public function test_disbursement_and_reject_use_the_in_page_dialog(): void
    {
        $this->user('kasir');
        $this->get('/pencairan')->assertOk()->assertSee('data-dialog="Cairkan pinjaman PIN-SETUJU"', false)->assertSee('data-dialog-ok="Cairkan"', false)
            ->assertSee('Cairkan Rp 5.000.000 ke Budi', false);
        $this->get("/pinjaman/{$this->approved->id}")->assertOk()->assertSee('data-dialog="Cairkan pinjaman PIN-SETUJU"', false);

        $this->user('admin');
        $this->get('/approval')->assertOk()->assertSee('data-dialog="Tolak pengajuan PIN-TUNGGU"', false)->assertSee('data-dialog-reason="Alasan penolakan"', false)
            ->assertSee('name="note"', false);
        $this->get("/pinjaman/{$this->pending->id}")->assertOk()->assertSee('data-dialog-reason="Alasan penolakan"', false);
    }

    public function test_member_password_reset_and_user_delete_use_the_dialog(): void
    {
        $this->user('admin');
        $this->post("/anggota/{$this->member->id}/akun")->assertSessionHasNoErrors();   // buat akun portal
        $this->get("/anggota/{$this->member->id}")->assertOk()->assertSee('data-dialog="Atur ulang kata sandi portal"', false);

        User::create(['role' => 'kasir', 'name' => 'Rina', 'email' => 'rina@x.test', 'password' => 'x']);
        $this->get('/pengaturan')->assertOk()->assertSee('data-dialog="Hapus pengguna Rina"', false)->assertSee('data-dialog-danger', false);
    }

    public function test_no_page_still_uses_the_browser_popups(): void
    {
        $this->user('admin');
        $this->post("/anggota/{$this->member->id}/akun");
        foreach (['/approval', "/pinjaman/{$this->approved->id}", "/pinjaman/{$this->pending->id}", "/anggota/{$this->member->id}", '/pengaturan'] as $url) {
            $this->get($url)->assertOk()->assertDontSee('confirm(', false)->assertDontSee('prompt(', false)->assertDontSee('onsubmit=', false);
        }
        $this->user('kasir');
        $this->get('/pencairan')->assertOk()->assertDontSee('confirm(', false)->assertDontSee('onsubmit=', false);
    }

    public function test_forms_with_a_dialog_fail_closed_until_app_js_is_ready(): void
    {
        // Pengaman di <head>: tanpa app.js (lambat, diblokir, atau salinan lama), form ber-data-dialog tidak boleh terkirim.
        $this->user('admin');
        $guard = $this->get('/approval')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<head>.*document\.addEventListener\(\'submit\'.*window\.dialogReady.*e\.preventDefault\(\).*<\/head>/s', $guard);
        $this->assertStringContainsString('window.dialogReady = true;', file_get_contents(public_path('assets/app.js')));
    }

    public function test_the_guard_script_really_blocks_and_releases_a_dialog_form(): void
    {
        // Jalankan skrip pengaman apa adanya (Node, tanpa browser) dengan "document" tiruan, supaya perilakunya yang diuji, bukan tulisannya.
        if (! trim((string) shell_exec('node -v 2>&1')) || ! str_starts_with(trim((string) shell_exec('node -v 2>&1')), 'v')) {
            $this->markTestSkipped('Node tidak tersedia.');
        }
        $this->user('admin');
        preg_match('/<script>(document\.addEventListener\(\'submit\'.*?)<\/script>/s', $this->get('/approval')->getContent(), $m);

        $harness = <<<'JS'
            let handler; global.window = {}; global.document = { addEventListener: (type, fn, capture) => { if (type === 'submit' && capture === true) handler = fn; } };
            %s
            const fire = target => { let blocked = false; handler({ target, preventDefault: () => { blocked = true; } }); return blocked; };
            const dialogForm = { dataset: { dialog: 'Cairkan' } };
            const plainForm = { dataset: {} };
            const out = [];
            out.push(fire(dialogForm));               // app.js belum siap: harus diblokir
            out.push(fire(plainForm));                // form biasa tidak terpengaruh
            window.dialogReady = true;
            out.push(fire(dialogForm));               // setelah siap: dilepas (dialog yang menjaga)
            console.log(JSON.stringify(out));
            JS;

        $file = tempnam(sys_get_temp_dir(), 'guard').'.cjs';
        file_put_contents($file, sprintf($harness, $m[1]));
        $result = trim((string) shell_exec('node '.escapeshellarg($file).' 2>&1'));
        unlink($file);

        $this->assertSame('[true,false,false]', $result);
    }
}
