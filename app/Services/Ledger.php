<?php

namespace App\Services;

use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// Satu pintu untuk mencatat transaksi + jurnal otomatis. Panggil di dalam DB::transaction milik pemanggil.
class Ledger
{
    public const KAS = ['10101', 'Kas Teller'];

    /**
     * Kunci kas untuk semua jalur kas keluar (pencairan, penarikan simpanan, pengeluaran kasir), lalu kembalikan saldo kas.
     * Pemanggil berikutnya menunggu transaksi ini selesai, jadi dua pengeluaran besar tidak bisa sama-sama lolos cek saldo.
     * Panggil sebagai pernyataan PERTAMA di dalam DB::transaction, sebelum memuat rekening atau pinjaman. Di MariaDB (REPEATABLE READ)
     * gambaran data transaksi dibekukan pada SELECT biasa pertama; bila kunci diambil setelahnya, saldo kas dibaca dari gambaran lama
     * dan pengeluaran pemanggil sebelumnya tidak terlihat. Urutan kunci selalu kas dulu, baru baris lain, jadi tidak bisa saling menunggu.
     */
    public static function lockCash(): float
    {
        $lock = fn () => DB::table('settings')->where('key', 'cash_lock')->lockForUpdate()->first();
        if (! $lock()) {
            // Baris pengunci dibuat sendiri pada pemakaian pertama. Kunci dicoba dulu karena INSERT yang bentrok di MariaDB memegang kunci bersama,
            // dan dua transaksi yang sama-sama memegangnya lalu meminta kunci eksklusif akan saling menunggu (deadlock).
            DB::table('settings')->insertOrIgnore(['key' => 'cash_lock']);
            $lock();
        }

        return JournalLine::cashBalance();
    }

    /**
     * @param array $trx  member_id, saving_id, loan_id (opsional), category, direction, amount, description
     * @param array $lines [[kode akun, nama akun, debit, kredit], ...]; total debit harus sama dengan total kredit
     * @return array{0:string,1:string} [nomor transaksi, nomor jurnal]
     */
    public static function post(array $trx, array $lines): array
    {
        $debit = round(array_sum(array_column($lines, 2)), 2);
        $credit = round(array_sum(array_column($lines, 3)), 2);
        if ($debit !== $credit) {
            throw new \LogicException("Jurnal tidak seimbang: debit $debit, kredit $credit");
        }

        $ref = now()->format('ymdHis').'-'.Str::upper(Str::random(5)); // 5 huruf acak: aman untuk banyak transaksi pada detik yang sama
        Transaction::create($trx + ['trx_no' => "TRX-$ref", 'trx_date' => now(), 'status' => 'Posted']); // trx_date boleh diisi pemanggil (saldo awal)

        $journal = JournalEntry::create([
            'journal_no' => "JU-$ref", 'journal_date' => $trx['trx_date'] ?? now(), 'reference' => "TRX-$ref",
            'description' => $trx['description'], 'source' => 'AUTO JOURNAL', 'status' => 'Posted',
        ]);
        foreach ($lines as [$code, $name, $dr, $cr]) {
            if ($dr > 0 || $cr > 0) {
                $journal->lines()->create(['account_code' => $code, 'account_name' => $name, 'debit' => $dr, 'credit' => $cr]);
            }
        }

        return ["TRX-$ref", "JU-$ref"];
    }
}
