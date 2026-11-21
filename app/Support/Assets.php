<?php

namespace App\Support;

// Alamat CSS dan JS dengan nomor versi dari waktu ubah file. Tanpa ini browser (dan service worker) bisa terus memakai app.js lama
// setelah halaman baru terpasang; skrip lama lalu gagal pada formulir baru dan kotak pilih anggota tidak aktif.
class Assets
{
    public static function url(string $path): string
    {
        $file = public_path($path);

        return asset($path) . (is_file($file) ? '?v=' . filemtime($file) : '');
    }
}
