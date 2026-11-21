<?php

namespace App\Support;

// Bagan akun (chart of accounts) koperasi. Satu-satunya daftar akun yang dikenal halaman Akuntansi.
// Konfirmasi susunan dan penamaan ke akuntan/auditor koperasi sebelum dipakai untuk laporan resmi.
class Coa
{
    // kode => [nama, golongan, saldo normal (D = debit, K = kredit)]
    public const ACCOUNTS = [
        '10101' => ['Kas Teller', 'Aset', 'D'],
        '11301' => ['Piutang Pinjaman', 'Aset', 'D'],
        '12101' => ['Perlengkapan Kantor', 'Aset', 'D'],
        '12201' => ['Aset Tetap', 'Aset', 'D'],
        '12301' => ['Akumulasi Penyusutan Aset Tetap', 'Aset', 'K'],
        '20201' => ['Simpanan Sukarela Anggota', 'Kewajiban', 'K'],
        '21101' => ['Utang Beban dan Utang Usaha', 'Kewajiban', 'K'],
        '31101' => ['Simpanan Pokok', 'Ekuitas', 'K'],
        '31102' => ['Simpanan Wajib', 'Ekuitas', 'K'],
        '31201' => ['Cadangan Koperasi', 'Ekuitas', 'K'],
        '39101' => ['Ekuitas Saldo Awal', 'Ekuitas', 'K'],
        '41101' => ['Pendapatan Bunga', 'Pendapatan', 'K'],
        '41201' => ['Pendapatan Administrasi dan Provisi', 'Pendapatan', 'K'],
        '41301' => ['Pendapatan Denda', 'Pendapatan', 'K'],
        '49101' => ['Pendapatan Lain-lain', 'Pendapatan', 'K'],
        '51101' => ['Beban Operasional', 'Beban', 'D'],
        '51201' => ['Beban Gaji dan Honor', 'Beban', 'D'],
        '51301' => ['Beban Listrik, Air dan Telepon', 'Beban', 'D'],
        '51401' => ['Beban ATK dan Perlengkapan', 'Beban', 'D'],
        '51501' => ['Beban Penyusutan', 'Beban', 'D'],
        '59101' => ['Beban Lain-lain', 'Beban', 'D'],
    ];

    // Akun yang saldonya harus sama dengan data transaksi (kas, piutang pinjaman, simpanan anggota):
    // tidak boleh dijurnal manual, ubah lewat menu Kasir, Pinjaman, atau Simpanan.
    public const SYSTEM = ['10101', '11301', '20201', '31101', '31102'];

    public static function name(string $code): string
    {
        return self::ACCOUNTS[$code][0] ?? $code;
    }

    public static function type(string $code): string
    {
        return self::ACCOUNTS[$code][1] ?? 'Lainnya';
    }

    // Saldo normal: +1 bila bertambah di debit (aset, beban), -1 bila bertambah di kredit.
    public static function sign(string $code): int
    {
        return (self::ACCOUNTS[$code][2] ?? 'D') === 'D' ? 1 : -1;
    }

    /** @return array<string,string> kode => nama untuk jurnal manual */
    public static function manual(): array
    {
        return collect(self::ACCOUNTS)->except(self::SYSTEM)->map(fn($a) => $a[0])->all();
    }
}
