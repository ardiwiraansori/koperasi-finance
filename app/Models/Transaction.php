<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    protected $fillable = ['trx_no','member_id','category','direction','amount','description','trx_date','status'];
    protected $casts = ['amount'=>'decimal:2','trx_date'=>'datetime'];
    public function member(): BelongsTo { return $this->belongsTo(Member::class); }
}
