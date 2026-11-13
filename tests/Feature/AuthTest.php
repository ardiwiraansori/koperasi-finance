<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_on_every_page(): void
    {
        foreach (['/dashboard', '/anggota', '/simpanan', '/simpanan/setoran', '/kasir', '/akuntansi'] as $url) {
            $this->get($url)->assertRedirect('/login');
        }
        $this->post('/kasir', [])->assertRedirect('/login');
        $this->post('/simpanan/setoran', [])->assertRedirect('/login');
    }

    public function test_login_and_logout(): void
    {
        User::create(['role' => 'admin', 'name' => 'Admin', 'email' => 'a@x.test', 'password' => 'rahasia-123']);

        $this->post('/login', ['email' => 'a@x.test', 'password' => 'salah'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post('/login', ['email' => 'a@x.test', 'password' => 'rahasia-123'])->assertRedirect('/dashboard');
        $this->assertAuthenticated();

        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_login_throttle_cannot_be_bypassed_with_fake_forwarded_ip(): void
    {
        $last = null;
        foreach (range(1, 8) as $i) {
            $last = $this->withHeaders(['X-Forwarded-For' => "10.0.0.$i"])
                ->post('/login', ['email' => 'a@x.test', 'password' => 'salah']);
        }
        $last->assertStatus(429);
    }
}
