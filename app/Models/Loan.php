<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Loan extends Model
{
    protected $fillable = ['member_id','loan_no','product','principal','interest_rate','term_months','installment','outstanding','status','applied_at','next_due_at'];
    protected $casts = ['principal'=>'decimal:2','interest_rate'=>'decimal:2','installment'=>'decimal:2','outstanding'=>'decimal:2','applied_at'=>'date','next_due_at'=>'date'];
    public function member(): BelongsTo { return $this->belongsTo(Member::class); }
}
