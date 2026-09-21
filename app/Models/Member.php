<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Member extends Model
{
    protected $fillable = ['member_no','name','phone','email','address','branch','joined_at','status'];
    protected $casts = ['joined_at' => 'date'];

    public function savings(): HasMany { return $this->hasMany(Saving::class); }
    public function loans(): HasMany { return $this->hasMany(Loan::class); }
    public function transactions(): HasMany { return $this->hasMany(Transaction::class); }
}
