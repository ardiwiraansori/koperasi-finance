<?php

namespace App\Models;

use App\Support\Coa;
use Illuminate\Database\Eloquent\Model;

class TransactionType extends Model
{
    public const CATEGORIES = ['Penerimaan', 'Pengeluaran'];

    // Golongan akun yang boleh dihubungkan: penerimaan ke pendapatan, pengeluaran ke beban.
    public const ACCOUNT_GROUP = ['Penerimaan' => 'Pendapatan', 'Pengeluaran' => 'Beban'];

    protected $fillable = ['category', 'name', 'account_code', 'active', 'sort'];
    protected $casts = ['active' => 'boolean'];

    public function scopeActive($q)
    {
        return $q->where('active', true);
    }
    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function accountName(): string
    {
        return Coa::name($this->account_code);
    }

    /** @return array<string,string> kode => nama akun yang boleh dipilih untuk kategori itu */
    public static function eligibleAccounts(string $category): array
    {
        return collect(Coa::ACCOUNTS)->filter(fn($a) => $a[1] === (self::ACCOUNT_GROUP[$category] ?? null))->map(fn($a) => $a[0])->all();
    }
}
