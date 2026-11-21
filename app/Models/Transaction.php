<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    protected $fillable = ['trx_no', 'member_id', 'saving_id', 'loan_id', 'transaction_type_id', 'category', 'direction', 'amount', 'description', 'trx_date', 'status'];
    protected $casts = ['amount' => 'decimal:2', 'trx_date' => 'datetime'];
    // Hanya transaksi yang benar-benar menggerakkan kas (saldo awal simpanan tidak).
    // Saldo awal simpanan dan pinjaman (go-live) mencatat saldo lama, bukan uang yang bergerak.
    public const NON_CASH = ['Saldo Awal Simpanan', 'Saldo Awal Pinjaman'];

    public function scopeCashOnly($q)
    {
        return $q->whereNotIn('category', self::NON_CASH);
    }
    // Arus kas operasional: tanpa saldo awal kas maupun saldo awal simpanan (bukan pemasukan/pengeluaran).
    public function scopeOperating($q)
    {
        return $q->whereNotIn('category', array_merge(['Saldo Awal'], self::NON_CASH));
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
    // Bukan "saving": bentrok dengan method event bawaan Eloquent.
    public function savingAccount(): BelongsTo
    {
        return $this->belongsTo(Saving::class, 'saving_id');
    }
}
