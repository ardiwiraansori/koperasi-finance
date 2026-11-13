<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Saving extends Model
{
    // Akun simpanan menurut praktik SAK EP koperasi: Pokok dan Wajib = ekuitas (31xxx), Sukarela = kewajiban (2xxxx).
    // Konfirmasi ke akuntan/auditor koperasi sebelum dipakai untuk laporan resmi.
    // Lawan saldo awal simpanan (kas tidak terlibat karena uangnya sudah ada sebelum aplikasi dipakai).
    public const OPENING = ['39101', 'Ekuitas Saldo Awal'];

    public const ACCOUNTS = [
        'Pokok' => ['31101', 'Simpanan Pokok'],
        'Wajib' => ['31102', 'Simpanan Wajib'],
        'Sukarela' => ['20201', 'Simpanan Sukarela Anggota'],
    ];

    protected $fillable = ['member_id','account_no','type','balance'];
    protected $casts = ['balance' => 'decimal:2'];
    public function member(): BelongsTo { return $this->belongsTo(Member::class); }
    public function transactions(): HasMany { return $this->hasMany(Transaction::class); }
}
