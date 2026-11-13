<?php

namespace Tests\Feature;

use App\Models\JournalEntry;
use App\Models\Transaction;
use App\Models\TransactionType;
use App\Models\User;
use App\Support\Coa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionTypeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \App\Models\Setting::put('has_accountant', '1'); // mode dengan akuntan: jurnal manual dan pemetaan akun tersedia
        $this->actingAs(User::create(['role' => 'admin', 'name' => 'Admin', 'email' => 'a@x.test', 'password' => 'x']));
        $open = JournalEntry::create(['journal_no' => 'JU-OPEN', 'journal_date' => '2020-01-01', 'description' => 'Saldo awal', 'source' => 'AUTO JOURNAL', 'status' => 'Posted']);
        $open->lines()->create(['account_code' => '10101', 'account_name' => 'Kas Teller', 'debit' => 10000000, 'credit' => 0]);
        $open->lines()->create(['account_code' => '39101', 'account_name' => 'Ekuitas Saldo Awal', 'debit' => 0, 'credit' => 10000000]);
    }

    private function id(string $name): int
    {
        return TransactionType::where('name', $name)->value('id');
    }

    private function spend(string $type, string $amount = '100.000')
    {
        return $this->post('/kasir', ['category' => 'Pengeluaran', 'jenis_id' => $this->id($type), 'amount' => $amount, 'description' => 'x']);
    }

    private function lastAccounts(): array
    {
        return JournalEntry::with('lines')->latest('id')->first()->lines->pluck('account_code')->all();
    }

    public function test_default_types_map_to_valid_accounts_of_the_right_group(): void
    {
        $this->assertGreaterThanOrEqual(8, TransactionType::count());
        foreach (TransactionType::all() as $t) {
            $this->assertSame(TransactionType::ACCOUNT_GROUP[$t->category], Coa::type($t->account_code), "{$t->name} dipetakan ke golongan akun yang salah");
        }
    }

    public function test_kasir_shows_plain_names_and_never_account_codes_or_account_names(): void
    {
        $page = $this->get('/kasir')->assertOk()->assertSee('Denda keterlambatan')->assertSee('Gaji dan honor')->assertSee('name="jenis_id"', false);
        $page->assertDontSee('name="account_code"', false)->assertDontSee('41301')->assertDontSee('Pendapatan Denda')->assertDontSee('Beban Gaji');
    }

    public function test_posting_uses_the_account_mapped_to_the_chosen_type_and_remembers_the_type(): void
    {
        $this->spend('Listrik, air, dan telepon')->assertSessionHasNoErrors();
        $this->assertEqualsCanonicalizing(['51301', '10101'], $this->lastAccounts());
        $this->assertDatabaseHas('transactions', ['category' => 'Pengeluaran', 'transaction_type_id' => $this->id('Listrik, air, dan telepon')]);

        $this->post('/kasir', ['category' => 'Penerimaan', 'jenis_id' => $this->id('Denda keterlambatan'), 'amount' => '5.000', 'description' => 'x'])->assertSessionHasNoErrors();
        $this->assertEqualsCanonicalizing(['41301', '10101'], $this->lastAccounts());
    }

    public function test_changing_the_mapping_affects_only_later_transactions(): void
    {
        $type = TransactionType::where('name', 'Biaya operasional kantor')->firstOrFail();
        $this->spend($type->name);
        $oldJournal = JournalEntry::latest('id')->first();

        $this->put("/akuntansi/jenis-transaksi/{$type->id}", ['name' => 'Biaya operasional kantor', 'account_code' => '51401', 'active' => '1'])->assertSessionHasNoErrors();
        $this->spend($type->name);

        $this->assertContains('51101', $oldJournal->fresh()->lines->pluck('account_code')->all());   // jurnal lama tidak berubah
        $this->assertEqualsCanonicalizing(['51401', '10101'], $this->lastAccounts());                // jurnal baru memakai akun baru
    }

    public function test_mapping_rules_group_and_uniqueness(): void
    {
        $type = TransactionType::where('name', 'Denda keterlambatan')->firstOrFail();
        $this->put("/akuntansi/jenis-transaksi/{$type->id}", ['name' => $type->name, 'account_code' => '51101'])->assertSessionHasErrors('account_code');  // penerimaan ke akun beban
        $this->put("/akuntansi/jenis-transaksi/{$type->id}", ['name' => $type->name, 'account_code' => '10101'])->assertSessionHasErrors('account_code');  // akun kas tidak boleh
        $this->put("/akuntansi/jenis-transaksi/{$type->id}", ['name' => 'Penerimaan lain-lain', 'account_code' => '41301'])->assertSessionHasErrors('name');  // nama kembar
        $this->assertSame('41301', $type->fresh()->account_code);

        $this->post('/akuntansi/jenis-transaksi', ['category' => 'Pengeluaran', 'name' => 'Sewa kantor', 'account_code' => '41101'])->assertSessionHasErrors('account_code');
        $this->post('/akuntansi/jenis-transaksi', ['category' => 'Pengeluaran', 'name' => 'Sewa kantor', 'account_code' => '51101'])->assertSessionHasNoErrors();
        $this->post('/akuntansi/jenis-transaksi', ['category' => 'Pengeluaran', 'name' => 'Sewa kantor', 'account_code' => '51101'])->assertSessionHasErrors('name');
        $this->post('/akuntansi/jenis-transaksi', ['category' => 'Penerimaan', 'name' => 'Sewa kantor', 'account_code' => '41301'])->assertSessionHasNoErrors(); // nama sama boleh beda kategori
        $this->post('/akuntansi/jenis-transaksi', ['category' => 'Angsuran', 'name' => 'x', 'account_code' => '41301'])->assertSessionHasErrors('category');
    }

    public function test_inactive_or_wrong_category_types_are_rejected_in_kasir(): void
    {
        $type = TransactionType::where('name', 'Gaji dan honor')->firstOrFail();
        $this->put("/akuntansi/jenis-transaksi/{$type->id}", ['name' => $type->name, 'account_code' => $type->account_code])->assertSessionHasNoErrors(); // active tidak dikirim = nonaktif
        $this->assertFalse($type->fresh()->active);

        $this->spend('Gaji dan honor')->assertSessionHasErrors('jenis_id');
        $this->get('/kasir')->assertDontSee('Gaji dan honor');
        $before = Transaction::count();
        $this->post('/kasir', ['category' => 'Penerimaan', 'jenis_id' => $this->id('Biaya operasional kantor'), 'amount' => '1.000', 'description' => 'x'])->assertSessionHasErrors('jenis_id');
        $this->assertSame($before, Transaction::count());
    }

    public function test_types_page_lists_usage_and_renders(): void
    {
        $this->spend('Gaji dan honor');
        $this->get('/akuntansi/jenis-transaksi')->assertOk()->assertSee('Jenis Transaksi Kasir')->assertSee('Gaji dan honor')->assertSee('51201 — Beban Gaji dan Honor')->assertSee('1×');
    }
}
