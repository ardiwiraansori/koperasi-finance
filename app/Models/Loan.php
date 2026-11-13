<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Loan extends Model
{
    // Status pinjaman yang masih berjalan (punya sisa pokok).
    public const RUNNING = ['Aktif', 'Tunggakan'];

    // Produk dan bunga tahunan (%), sama dengan data awal. Ubah di sini bila kebijakan koperasi berbeda.
    public const PRODUCTS = ['Pinjaman Reguler' => 12, 'Pinjaman Usaha' => 11, 'Pinjaman Pendidikan' => 9];

    protected $fillable = ['member_id','loan_no','product','principal','interest_rate','term_months','installment','outstanding','status','applied_at','disbursed_at','next_due_at','note'];
    protected $casts = ['principal'=>'decimal:2','interest_rate'=>'decimal:2','installment'=>'decimal:2','outstanding'=>'decimal:2','applied_at'=>'date','disbursed_at'=>'date','next_due_at'=>'date'];

    public function member(): BelongsTo { return $this->belongsTo(Member::class); }
    public function transactions(): HasMany { return $this->hasMany(Transaction::class); }

    // Cicilan tetap bulanan (anuitas), dibulatkan ke rupiah.
    public static function annuity(float $principal, float $annualRate, int $months): float
    {
        $r = $annualRate / 1200;
        return round($r > 0 ? $principal * $r / (1 - (1 + $r) ** -$months) : $principal / $months);
    }

    // Hari lewat jatuh tempo (0 bila belum atau pinjaman tidak berjalan).
    public function daysOverdue(): int
    {
        return in_array($this->status, self::RUNNING, true) && $this->next_due_at?->lt(today())
            ? (int) $this->next_due_at->diffInDays(today())
            : 0;
    }

    // Status tampilan: pinjaman berjalan otomatis "Tunggakan" bila lewat jatuh tempo, tanpa menunggu ada yang mengubah data.
    protected function displayStatus(): Attribute
    {
        return Attribute::get(fn () => in_array($this->status, self::RUNNING, true) ? ($this->daysOverdue() > 0 ? 'Tunggakan' : 'Aktif') : $this->status);
    }

    // Jadwal sisa angsuran pinjaman berjalan: dari sisa pokok sekarang, mulai jatuh tempo berikutnya, dengan cicilan yang berlaku.
    // Berhenti bila cicilan tidak menutup bunga (data tidak masuk akal) agar tidak berputar tanpa akhir.
    public function remainingSchedule(): array
    {
        $balance = (float) $this->outstanding;
        $r = (float) $this->interest_rate / 1200;
        $first = ($this->next_due_at ?? today())->copy();
        $rows = [];
        for ($n = 1; $balance > 0.5 && $n <= 600; $n++) {
            $interest = round($balance * $r);
            $principal = min(max((float) $this->installment - $interest, 0), $balance);
            if ($principal <= 0) {
                break;
            }
            $balance = (float) max(0, $balance - $principal);
            $rows[] = ['no' => $n, 'due' => $first->copy()->addMonthsNoOverflow($n - 1), 'principal' => $principal, 'interest' => $interest, 'total' => $principal + $interest, 'balance' => $balance];
        }
        return $rows;
    }

    // Jadwal angsuran anuitas dari tanggal pencairan (atau hari ini bila belum dicairkan).
    public function schedule(): array
    {
        $start = ($this->disbursed_at ?? today())->copy();
        $balance = (float) $this->principal;
        $r = (float) $this->interest_rate / 1200;
        $rows = [];
        for ($n = 1; $n <= $this->term_months; $n++) {
            $interest = round($balance * $r);
            $principal = $n === $this->term_months ? $balance : (float) $this->installment - $interest; // cicilan terakhir melunasi sisa
            $balance = (float) max(0, $balance - $principal);
            $rows[] = ['no' => $n, 'due' => $start->copy()->addMonthsNoOverflow($n), 'principal' => $principal, 'interest' => $interest, 'total' => $principal + $interest, 'balance' => $balance];
        }
        return $rows;
    }
}
