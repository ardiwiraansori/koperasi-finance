<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

// Migrasi kolom pinjaman berjalan tanpa transaksi, jadi harus aman diulang bila deploy terputus di tengah jalan.
class LoanMigrationsTest extends TestCase
{
    use RefreshDatabase;

    private const COLUMNS = ['applied_by', 'approved_by', 'disbursed_by', 'settled_by_loan_id'];
    private const FILES = ['2026_11_22_000001_add_actors_to_loans', '2026_11_22_000002_add_settled_by_to_loans'];

    public function test_columns_exist_and_the_migrations_can_run_again_without_error(): void
    {
        foreach (self::COLUMNS as $column) {
            $this->assertTrue(Schema::hasColumn('loans', $column), "kolom $column");
        }

        foreach (self::FILES as $file) {
            $migration = require database_path("migrations/$file.php");
            $this->assertFalse($migration->withinTransaction);   // alasan di komentar migrasi: galat asli tertutup "transaction is aborted"
            $migration->up();                                    // diulang: kolom yang sudah ada dilewati
        }

        foreach (self::COLUMNS as $column) {
            $this->assertTrue(Schema::hasColumn('loans', $column));
        }
    }

    public function test_a_partly_applied_deploy_is_completed_by_the_next_run(): void
    {
        foreach (array_reverse(self::FILES) as $file) {
            (require database_path("migrations/$file.php"))->down();
        }
        $this->assertFalse(Schema::hasColumn('loans', 'applied_by'));
        $this->assertFalse(Schema::hasColumn('loans', 'settled_by_loan_id'));

        (require database_path('migrations/'.self::FILES[0].'.php'))->up();    // deploy putus setelah migrasi pertama
        $this->assertTrue(Schema::hasColumn('loans', 'disbursed_by'));
        $this->assertFalse(Schema::hasColumn('loans', 'settled_by_loan_id'));

        foreach (self::FILES as $file) {
            (require database_path("migrations/$file.php"))->up();             // deploy berikutnya menjalankan keduanya lagi
        }
        foreach (self::COLUMNS as $column) {
            $this->assertTrue(Schema::hasColumn('loans', $column));
        }
    }
}
