<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JournalEntry extends Model
{
    protected $fillable = ['journal_no','journal_date','reference','description','source','status'];
    protected $casts = ['journal_date'=>'date'];
    public function lines(): HasMany { return $this->hasMany(JournalLine::class); }
}
