<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::create(['role' => 'admin', 'name' => 'Admin', 'email' => 'a@x.test', 'password' => 'x']));
    }

    private function payload(array $override = []): array
    {
        return $override + [
            'name' => 'Siti Aminah', 'nik' => '3201234567890001', 'gender' => 'P', 'birth_place' => 'Bogor', 'birth_date' => '1990-05-17',
            'marital_status' => 'Kawin', 'occupation' => 'Pedagang', 'phone' => '081234567890', 'email' => 'siti@example.test', 'address' => 'Jl. Melati 5',
            'heir_name' => 'Budi', 'heir_relation' => 'Suami', 'heir_phone' => '081298765432',
            'branch' => 'Pusat', 'status' => 'Aktif', 'joined_at' => '2026-01-10',
        ];
    }

    public function test_create_stores_all_profile_fields(): void
    {
        $this->post('/anggota', $this->payload())->assertSessionHasNoErrors();

        $m = Member::where('name', 'Siti Aminah')->firstOrFail();
        $this->assertSame(['3201234567890001', 'P', 'Bogor', '1990-05-17', 'Kawin', 'Pedagang'], [$m->nik, $m->gender, $m->birth_place, $m->birth_date->toDateString(), $m->marital_status, $m->occupation]);
        $this->assertSame(['Budi', 'Suami', '081298765432'], [$m->heir_name, $m->heir_relation, $m->heir_phone]);
    }

    public function test_only_name_branch_status_and_joined_date_are_required(): void
    {
        $this->post('/anggota', ['name' => 'Minimal', 'branch' => 'Pusat', 'status' => 'Aktif', 'joined_at' => '2026-01-10'])->assertSessionHasNoErrors();
        $m = Member::where('name', 'Minimal')->firstOrFail();
        $this->assertNull($m->nik);
        $this->assertNull($m->birth_date);

        // dua anggota tanpa NIK tidak boleh bentrok pada kolom unik (kosong harus NULL, bukan "")
        $this->post('/anggota', ['name' => 'Minimal Dua', 'branch' => 'Pusat', 'status' => 'Aktif', 'joined_at' => '2026-01-10'])->assertSessionHasNoErrors();
        $this->assertSame(2, Member::whereNull('nik')->count());
    }

    public function test_nik_rules(): void
    {
        $this->post('/anggota', $this->payload(['nik' => '1234']))->assertSessionHasErrors('nik');               // bukan 16 angka
        $this->post('/anggota', $this->payload(['nik' => '32012345678900019']))->assertSessionHasErrors('nik');   // 17 angka
        $this->assertSame(0, Member::count());

        $this->post('/anggota', $this->payload(['nik' => '3201 2345 6789 0001']))->assertSessionHasNoErrors();   // spasi dibuang
        $this->assertSame('3201234567890001', Member::firstOrFail()->nik);

        $this->post('/anggota', $this->payload(['name' => 'Dobel', 'email' => 'lain@example.test']))->assertSessionHasErrors('nik');   // NIK ganda
        $this->assertSame(1, Member::count());
    }

    public function test_update_may_keep_its_own_nik_but_not_take_anothers(): void
    {
        $this->post('/anggota', $this->payload())->assertSessionHasNoErrors();
        $this->post('/anggota', $this->payload(['name' => 'Kedua', 'nik' => '3201234567890002', 'email' => 'dua@example.test']))->assertSessionHasNoErrors();
        [$a, $b] = [Member::where('name', 'Siti Aminah')->first(), Member::where('name', 'Kedua')->first()];

        $this->put("/anggota/{$a->id}", $this->payload(['occupation' => 'Guru']))->assertSessionHasNoErrors();
        $this->assertSame('Guru', $a->fresh()->occupation);

        $this->put("/anggota/{$b->id}", $this->payload(['name' => 'Kedua', 'email' => 'dua@example.test']))->assertSessionHasErrors('nik');   // NIK milik Siti
        $this->assertSame('3201234567890002', $b->fresh()->nik);
    }

    public function test_other_field_validation(): void
    {
        $bad = fn (array $o, string $field) => $this->post('/anggota', $this->payload($o + ['email' => uniqid().'@example.test', 'nik' => null]))->assertSessionHasErrors($field);

        $bad(['gender' => 'X'], 'gender');
        $bad(['marital_status' => 'Rumit'], 'marital_status');
        $bad(['birth_date' => now()->subYears(16)->toDateString()], 'birth_date');   // minimal 17 tahun
        $bad(['birth_date' => now()->addDay()->toDateString()], 'birth_date');
        $bad(['birth_date' => '1800-01-01'], 'birth_date');
        $bad(['heir_relation' => 'Tetangga'], 'heir_relation');
        $bad(['heir_relation' => null], 'heir_relation');                            // nama ahli waris diisi, hubungan kosong
        $bad(['heir_phone' => '12345'], 'heir_phone');
        $bad(['occupation' => str_repeat('a', 81)], 'occupation');
        $this->assertSame(0, Member::count());

        $this->post('/anggota', $this->payload(['nik' => null, 'heir_name' => null, 'heir_relation' => null, 'heir_phone' => null]))->assertSessionHasNoErrors(); // tanpa ahli waris boleh
        $this->post('/anggota', $this->payload(['name' => 'Tepat 17', 'nik' => null, 'email' => 'x@example.test', 'birth_date' => now()->subYears(17)->toDateString()]))->assertSessionHasNoErrors();
    }

    public function test_clearing_the_heir_name_clears_the_rest_of_the_heir_data(): void
    {
        $this->post('/anggota', $this->payload())->assertSessionHasNoErrors();
        $m = Member::firstOrFail();
        $this->assertSame(['Budi', 'Suami', '081298765432'], [$m->heir_name, $m->heir_relation, $m->heir_phone]);

        // hanya nama yang dihapus; hubungan dan HP masih terkirim dari form
        $this->put("/anggota/{$m->id}", $this->payload(['heir_name' => null]))->assertSessionHasNoErrors();
        $m->refresh();
        $this->assertSame([null, null, null], [$m->heir_name, $m->heir_relation, $m->heir_phone]);
        $this->get("/anggota/{$m->id}/formulir")->assertOk()->assertDontSee('Suami');

        // mengisi lagi tetap berfungsi
        $this->put("/anggota/{$m->id}", $this->payload(['heir_name' => 'Ani', 'heir_relation' => 'Anak', 'heir_phone' => null]))->assertSessionHasNoErrors();
        $this->assertSame(['Ani', 'Anak', null], [$m->fresh()->heir_name, $m->fresh()->heir_relation, $m->fresh()->heir_phone]);
    }

    public function test_form_pages_show_sections_and_keep_old_input_after_an_error(): void
    {
        $this->get('/anggota/tambah')->assertOk()->assertSee('Data Pribadi')->assertSee('Kontak dan Alamat')->assertSee('Ahli Waris')->assertSee('Keanggotaan')
            ->assertSee('name="gender"', false)->assertSee('Simpanan Pokok (setoran awal)');

        $this->from('/anggota/tambah')->post('/anggota', $this->payload(['nik' => '123']));
        $this->get('/anggota/tambah')->assertSee('value="Siti Aminah"', false)->assertSee('NIK harus 16 angka')->assertSee('aria-invalid="true"', false);

        $this->post('/anggota', $this->payload())->assertSessionHasNoErrors();
        $m = Member::firstOrFail();
        $this->get("/anggota/{$m->id}/ubah")->assertOk()->assertSee('value="3201234567890001"', false)->assertSee('value="1990-05-17"', false)
            ->assertDontSee('Simpanan Pokok (setoran awal)')->assertSee('Simpan Perubahan');
    }

    public function test_profile_shows_the_new_data_and_prompts_to_complete_missing_data(): void
    {
        $this->post('/anggota', $this->payload())->assertSessionHasNoErrors();
        $m = Member::firstOrFail();
        $this->get("/anggota/{$m->id}")->assertOk()->assertSee('3201234567890001')->assertSee('Perempuan')->assertSee('Bogor, 17 Mei 1990')->assertSee('Pedagang')
            ->assertSee('Budi')->assertSee('Suami')->assertSeeCollapsed('Cetak Formulir');

        $empty = Member::create(['member_no' => 'AG000900', 'name' => 'Kosong', 'branch' => 'Pusat', 'joined_at' => '2026-01-01', 'status' => 'Aktif']);
        $this->get("/anggota/{$empty->id}")->assertOk()->assertSee('—')->assertSee('Lengkapi');
    }

    public function test_printable_sheet_contains_the_data_the_statement_and_signatures(): void
    {
        Setting::put('koperasi_name', 'Koperasi Sejahtera');
        $this->post('/anggota', $this->payload(['simpanan_pokok' => '100.000']))->assertSessionHasNoErrors();
        $m = Member::firstOrFail();

        $page = $this->get("/anggota/{$m->id}/formulir")->assertOk();
        $page->assertSee('FORMULIR KEANGGOTAAN')->assertSee('Koperasi Sejahtera')->assertSee($m->member_no)->assertSee('Siti Aminah')->assertSee('3201234567890001')
            ->assertSee('Bogor, 17 Mei 1990')->assertSee('Pedagang')->assertSee('Rp 100.000')->assertSee('Saldo Simpanan Pokok')->assertSee('Anggaran Dasar')
            ->assertSee('Anggota,')->assertSee('Pengurus / Petugas,')->assertSeeCollapsed('Cetak Formulir');
    }

    public function test_sheet_for_an_incomplete_member_shows_blank_lines_not_errors(): void
    {
        $m = Member::create(['member_no' => 'AG000901', 'name' => 'Kosong', 'branch' => 'Pusat', 'joined_at' => '2026-01-01', 'status' => 'Aktif']);
        $this->get("/anggota/{$m->id}/formulir")->assertOk()->assertSee('class="blank"', false)->assertSee('Kosong');
    }

    public function test_sheet_escapes_html_in_member_data(): void
    {
        $m = Member::create(['member_no' => 'AG000902', 'name' => '<script>alert(1)</script>', 'address' => '"><b>x</b>', 'branch' => 'Pusat', 'joined_at' => '2026-01-01', 'status' => 'Aktif']);
        $this->get("/anggota/{$m->id}/formulir")->assertOk()->assertDontSee('<script>alert(1)</script>', false)->assertDontSee('<b>x</b>', false);
        $this->get("/anggota/{$m->id}")->assertOk()->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_roles_and_guests(): void
    {
        $m = Member::create(['member_no' => 'AG000903', 'name' => 'Rina', 'branch' => 'Pusat', 'joined_at' => '2026-01-01', 'status' => 'Aktif']);

        $this->actingAs(User::create(['role' => 'kasir', 'name' => 'Kasir', 'email' => 'k@x.test', 'password' => 'x']));
        $this->get("/anggota/{$m->id}/formulir")->assertOk();                       // kasir boleh mencetak
        $this->get("/anggota/{$m->id}/ubah")->assertOk();                           // dan mengisi data

        $this->actingAs(User::create(['role' => 'akuntan', 'name' => 'Akt', 'email' => 'ak@x.test', 'password' => 'x']));
        $this->get("/anggota/{$m->id}/formulir")->assertOk();                       // akuntan boleh melihat
        $this->get("/anggota/{$m->id}/ubah")->assertForbidden();                    // tetapi tidak mengubah

        $this->get('/anggota/99999/formulir')->assertNotFound();
        auth()->logout();
        $this->app['auth']->forgetGuards();
        $this->get("/anggota/{$m->id}/formulir")->assertRedirect('/login');
    }
}
