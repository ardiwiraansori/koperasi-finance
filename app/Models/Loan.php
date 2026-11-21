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

    // Satu produk dengan bunga tetap 12,5% per tahun (keputusan koperasi). Pinjaman lama yang sudah tercatat tetap memakai bunganya sendiri.
    public const PRODUCT = 'Pinjaman Reguler';
    public const RATE = 12.5;

    protected $fillable = ['member_id', 'loan_no', 'product', 'principal', 'interest_rate', 'term_months', 'installment', 'outstanding', 'status', 'applied_at', 'disbursed_at', 'next_due_at', 'note', 'applied_by', 'approved_by', 'disbursed_by', 'settled_by_loan_id'];
    protected $casts = ['principal' => 'decimal:2', 'interest_rate' => 'decimal:2', 'installment' => 'decimal:2', 'outstanding' => 'decimal:2', 'applied_at' => 'date', 'disbursed_at' => 'date', 'next_due_at' => 'date'];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
    public function appliedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applied_by');
    }
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
    public function disbursedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disbursed_by');
    }
    public function settledBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'settled_by_loan_id');
    }
    public function settledLoans(): HasMany
    {
        return $this->hasMany(self::class, 'settled_by_loan_id');
    }

    // Bunga bulan berjalan dari sisa pokok. Satu rumus untuk angsuran di kasir dan pelunasan top-up.
    public function interestDue(): float
    {
        return round((float) $this->outstanding * (float) $this->interest_rate / 1200);
    }

    // Jumlah jatuh tempo yang sudah lewat dan belum dibayar: 1 untuk pinjaman lancar (bulan berjalan), lebih banyak bila menunggak.
    // Jatuh tempo jatuh tiap bulan mulai dari next_due_at, dihitung yang tanggalnya sudah lewat (hari ini belum lewat):
    // terlambat 95 hari berarti 4 jatuh tempo terlewat (sekitar hari ke 0, 30, 60, 90).
    public function missedMonths(): int
    {
        if ($this->daysOverdue() === 0) {
            return 1;
        }
        $n = 1;
        while ($this->next_due_at->copy()->addMonthsNoOverflow($n)->lt(today())) {
            $n++;
        }

        return $n;
    }

    // Bunga yang harus dilunasi pada top-up: bunga sebulan dikali jumlah bulan yang belum dibayar. Pinjaman yang menunggak tidak boleh
    // lolos dengan bunga satu bulan saja, karena bunga bulan yang terlewat tidak tercatat di mana pun. Angsuran di kasir tetap sebulan (interestDue).
    public function payoffInterest(): float
    {
        return $this->interestDue() * $this->missedMonths();
    }

    // Kewajiban pelunasan semua pinjaman berjalan seorang anggota (top-up): sisa pokok + bunga yang belum dibayar per pinjaman.
    // Dipanggil di dalam transaksi pencairan dengan $lock = true agar angkanya dihitung dari baris yang terkunci.
    public static function payoffLines(int $memberId, bool $lock = false): array
    {
        $query = self::where('member_id', $memberId)->whereIn('status', self::RUNNING)->orderBy('id');

        return self::payoffOf($lock ? $query->lockForUpdate()->get() : $query->get());
    }

    // Rincian pelunasan untuk kumpulan pinjaman berjalan: sisa pokok, bunga, jumlah bulan bunga, total, dan hari terlambat.
    public static function payoffOf($loans): array
    {
        return $loans->map(function (self $l) {
            $interest = $l->payoffInterest();

            return ['loan' => $l, 'outstanding' => (float) $l->outstanding, 'interest' => $interest, 'months' => $l->missedMonths(), 'total' => (float) $l->outstanding + $interest, 'days' => $l->daysOverdue()];
        })->values()->all();
    }

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
        return Attribute::get(fn() => in_array($this->status, self::RUNNING, true) ? ($this->daysOverdue() > 0 ? 'Tunggakan' : 'Aktif') : $this->status);
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
