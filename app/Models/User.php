<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    public const ROLES = ['admin' => 'Admin / Pengurus', 'kasir' => 'Kasir', 'akuntan' => 'Akuntan'];

    // Hak akses per fitur. Kasir hanya menginput; keputusan (persetujuan pinjaman) dan pengaturan di tangan admin;
    // akuntansi, laporan, dan SHU untuk admin dan akuntan.
    private const ABILITIES = [
        'cash' => ['admin', 'kasir'],              // Kasir / Teller
        'members.write' => ['admin', 'kasir'],     // tambah dan ubah anggota
        'savings.tx' => ['admin', 'kasir'],        // setoran dan penarikan simpanan
        'loan.apply' => ['admin', 'kasir'],        // mengajukan pinjaman
        'loan.decide' => ['admin'],                // setujui, tolak, cairkan
        'opening' => ['admin', 'akuntan'],         // saldo awal kas, simpanan, pinjaman
        'accounting' => ['admin', 'akuntan'],      // akuntansi, laporan, SHU
        'export' => ['admin', 'akuntan'],          // unduh daftar anggota
        'types.edit' => ['admin', 'akuntan'],      // jenis transaksi kasir
        'settings' => ['admin'],                   // pengaturan dan pengguna
    ];

    protected $fillable = ['name', 'email', 'password', 'role', 'member_id'];
    protected $hidden = ['password', 'remember_token'];
    protected $casts = ['password' => 'hashed'];

    public function allows(string $ability): bool
    {
        // Jurnal manual hanya ada bila koperasi memiliki akuntan.
        if ($ability === 'journal.manual') {
            return Setting::hasAccountant() && in_array($this->role, ['admin', 'akuntan'], true);
        }
        return in_array($this->role, self::ABILITIES[$ability] ?? [], true);
    }

    public function member(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(Member::class); }

    public function roleLabel(): string { return self::ROLES[$this->role] ?? ucfirst($this->role); }
}
