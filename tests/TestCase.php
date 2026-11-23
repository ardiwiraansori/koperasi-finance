<?php

namespace Tests;

use App\Models\Setting;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Assert;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Setting::forget(); // cache pengaturan bersifat statis: kosongkan agar tiap tes mulai bersih

        // Perapi kode (formatter Blade) bisa memecah teks atau atribut di dalam tag menjadi beberapa baris. Tampilan tidak berubah
        // karena browser meringkas spasi, jadi assertSeeCollapsed membandingkan HTML dengan spasi dan baris baru diringkas.
        if (! TestResponse::hasMacro('assertSeeCollapsed')) {
            TestResponse::macro('assertSeeCollapsed', function (string $value, bool $escape = true) {
                $collapse = fn (string $s) => preg_replace('/\s+/', ' ', $s);
                Assert::assertStringContainsString($collapse($escape ? e($value) : $value), $collapse($this->getContent()));

                return $this;
            });
        }
    }

    // Sesi tes dipakai ulang antar permintaan. Berganti pengguna di tengah tes harus berarti sesi baru, seperti login di perangkat lain;
    // kalau tidak, sidik sandi pengguna sebelumnya masih tersimpan di sesi dan AuthenticateSession menolak pengguna yang baru.
    public function actingAs(Authenticatable $user, $guard = null)
    {
        $this->flushSession();

        return parent::actingAs($user, $guard);
    }
}
