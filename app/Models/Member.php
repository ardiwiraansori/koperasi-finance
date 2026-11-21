<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Member extends Model
{
    public const GENDERS = ['L' => 'Laki-laki', 'P' => 'Perempuan'];
    public const MARITAL = ['Belum Kawin', 'Kawin', 'Cerai Hidup', 'Cerai Mati'];
    public const HEIR_RELATIONS = ['Suami', 'Istri', 'Anak', 'Orang Tua', 'Saudara', 'Lainnya'];

    protected $fillable = ['member_no', 'name', 'nik', 'gender', 'birth_place', 'birth_date', 'marital_status', 'occupation', 'phone', 'email', 'address', 'heir_name', 'heir_relation', 'heir_phone', 'branch', 'joined_at', 'status'];
    protected $casts = ['joined_at' => 'date', 'birth_date' => 'date'];

    public function portalUser(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(User::class);
    }
    public function savings(): HasMany
    {
        return $this->hasMany(Saving::class);
    }
    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
