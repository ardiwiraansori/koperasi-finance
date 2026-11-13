<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

// CSV berpemisah titik koma dengan BOM: terbuka rapi di Excel berbahasa Indonesia.
class Csv
{
    // Awalan ' mencegah sel diperlakukan sebagai rumus Excel (=, +, -, @) bila isian pengguna diawali karakter itu.
    public static function safe(mixed $v): mixed
    {
        return is_string($v) && $v !== '' && str_contains('=+-@', $v[0]) ? "'".$v : $v;
    }

    public static function download(string $filename, array $header, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($header, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $header, ';');
            foreach ($rows as $row) {
                fputcsv($out, array_map([self::class, 'safe'], $row), ';');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
