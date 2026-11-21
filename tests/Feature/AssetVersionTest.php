<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// CSS dan JS memakai nomor versi dari waktu ubah file, supaya browser tidak memakai app.js lama dengan halaman baru.
class AssetVersionTest extends TestCase
{
    use RefreshDatabase;

    public function test_assets_carry_a_version_taken_from_the_file(): void
    {
        $css = filemtime(public_path('assets/app.css'));
        $js = filemtime(public_path('assets/app.js'));

        $this->get('/login')->assertOk()->assertSee("assets/app.css?v=$css", false);

        $this->actingAs(User::create(['role' => 'kasir', 'name' => 'Kasir', 'email' => 'k@x.test', 'password' => 'x']));
        $this->get('/dashboard')->assertOk()->assertSee("assets/app.css?v=$css", false)->assertSee("assets/app.js?v=$js", false);
    }
}
