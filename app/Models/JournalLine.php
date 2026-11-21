<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalLine extends Model
{
    protected $fillable = ['journal_entry_id', 'account_code', 'account_name', 'debit', 'credit'];
    protected $casts = ['debit' => 'decimal:2', 'credit' => 'decimal:2'];
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    // Saldo kas = total debit - kredit akun Kas Teller (10101) pada jurnal yang sudah Posted.
    public static function cashBalance(): float
    {
        return (float) static::where('account_code', '10101')
            ->whereHas('journalEntry', fn($q) => $q->where('status', 'Posted'))
            ->selectRaw('COALESCE(SUM(debit - credit), 0) as balance')
            ->value('balance');
    }
}
