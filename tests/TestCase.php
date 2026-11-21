<?php

namespace Tests;

use App\Models\Setting;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Setting::forget(); // cache pengaturan bersifat statis: kosongkan agar tiap tes mulai bersih
    }

    // Sesi tes dipakai ulang antar permintaan. Berganti pengguna di tengah tes harus berarti sesi baru, seperti login di perangkat lain;
    // kalau tidak, sidik sandi pengguna sebelumnya masih tersimpan di sesi dan AuthenticateSession menolak pengguna yang baru.
    public function actingAs(Authenticatable $user, $guard = null)
    {
        $this->flushSession();

        return parent::actingAs($user, $guard);
    }
}
