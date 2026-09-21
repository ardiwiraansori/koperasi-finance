<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Saving extends Model
{
    protected $fillable = ['member_id','account_no','type','balance'];
    protected $casts = ['balance' => 'decimal:2'];
    public function member(): BelongsTo { return $this->belongsTo(Member::class); }
}
