<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordTest extends TestCase
{
    use RefreshDatabase;

    private function change(array $override = [])
    {
        return $this->put('/kata-sandi', $override + ['current_password' => 'lama12345', 'password' => 'baru12345', 'password_confirmation' => 'baru12345']);
    }

    public function test_staff_changes_own_password_with_the_old_one_and_stays_logged_in(): void
    {
        $user = User::create(['role' => 'kasir', 'name' => 'Kasir', 'email' => 'k@x.test', 'password' => 'lama12345']);
        $this->actingAs($user);

        $this->get('/kata-sandi')->assertOk()->assertSee('Ganti Kata Sandi');
        $this->change()->assertRedirect('/kata-sandi')->assertSessionHas('success');

        $this->assertTrue(Hash::check('baru12345', $user->fresh()->password));
        $this->get('/kata-sandi')->assertOk();   // sesi ini tidak ikut keluar
    }

    public function test_wrong_old_password_or_weak_new_password_is_rejected(): void
    {
        $user = User::create(['role' => 'kasir', 'name' => 'Kasir', 'email' => 'k@x.test', 'password' => 'lama12345']);
        $this->actingAs($user);

        $this->change(['current_password' => 'salah'])->assertSessionHasErrors('current_password');
        $this->change(['password' => 'pendek', 'password_confirmation' => 'pendek'])->assertSessionHasErrors('password');
        $this->change(['password_confirmation' => 'beda12345'])->assertSessionHasErrors('password');
        $this->change(['password' => 'lama12345', 'password_confirmation' => 'lama12345'])->assertSessionHasErrors('password');   // harus beda dari yang lama

        $this->assertTrue(Hash::check('lama12345', $user->fresh()->password));
    }

    public function test_member_changes_password_on_the_portal_page(): void
    {
        $member = Member::create(['member_no' => 'AG000001', 'name' => 'Ani', 'branch' => 'Pusat', 'joined_at' => '2026-01-01', 'status' => 'Aktif']);
        $user = User::create(['role' => 'anggota', 'member_id' => $member->id, 'name' => 'Ani', 'email' => 'ag000001@anggota.local', 'password' => 'lama12345']);
        $this->actingAs($user);

        $this->get('/kata-sandi')->assertOk()->assertSee('Kembali ke akun saya');   // halaman portal, bukan menu petugas
        $this->get('/portal')->assertOk()->assertSee('Ganti Kata Sandi');
        $this->change()->assertSessionHas('success');
        $this->assertTrue(Hash::check('baru12345', $user->fresh()->password));
    }

    public function test_guest_cannot_open_the_page(): void
    {
        $this->get('/kata-sandi')->assertRedirect('/login');
        $this->change()->assertRedirect('/login');
    }

    public function test_admin_changing_own_password_in_settings_stays_logged_in(): void
    {
        $admin = User::create(['role' => 'admin', 'name' => 'Admin', 'email' => 'a@x.test', 'password' => 'lama12345']);
        $this->actingAs($admin);

        $this->put(route('settings.users.update', $admin), ['name' => 'Admin', 'email' => 'a@x.test', 'role' => 'admin', 'password' => 'baru12345'])
            ->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('baru12345', $admin->fresh()->password));

        // Di produksi permintaan berikutnya memuat pengguna dari database; di tes objek yang sama dipakai ulang, jadi muat ulang di sini.
        auth()->setUser($admin->fresh());
        $this->get('/dashboard')->assertOk();
    }

    public function test_a_session_opened_with_an_old_password_is_ended(): void
    {
        $user = User::create(['role' => 'kasir', 'name' => 'Kasir', 'email' => 'k@x.test', 'password' => 'lama12345']);

        // Sesi di perangkat lain menyimpan sidik sandi lama; admin atau pemiliknya sudah mengganti sandi sejak itu.
        $this->actingAs($user)->withSession(['password_hash_web' => 'sidik-sandi-lama'])->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }
}
