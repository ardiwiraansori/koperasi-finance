<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\Saving;
use App\Models\User;
use App\Services\Ledger;
use App\Support\Csv;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MemberController extends Controller
{
    public const BRANCHES = ['Pusat', 'Cabang A', 'Cabang B'];
    public const STATUSES = ['Aktif', 'Nonaktif'];

    public function index(): View
    {
        $members = Member::withSum('savings', 'balance')->withSum('loans', 'outstanding')->orderBy('member_no')->get();
        return view('members.index', compact('members'));
    }

    // CSV berpemisah titik koma dengan BOM: terbuka rapi di Excel berbahasa Indonesia.
    public function export(): StreamedResponse
    {
        $members = Member::withSum('savings', 'balance')->withSum('loans as running_loans_sum', 'outstanding')->orderBy('member_no')->get();

        return Csv::download(
            'anggota-' . now()->format('Ymd') . '.csv',
            ['No Anggota', 'Nama', 'Telepon', 'Email', 'Alamat', 'Cabang', 'Bergabung', 'Status', 'Total Simpanan', 'Sisa Pinjaman'],
            $members->map(fn($m) => [$m->member_no, $m->name, $m->phone, $m->email, $m->address, $m->branch, $m->joined_at->format('Y-m-d'), $m->status, (int) $m->savings_sum_balance, (int) $m->running_loans_sum])
        );
    }

    public function show(Member $member): View
    {
        $member->load(['savings', 'loans' => fn($q) => $q->latest('applied_at'), 'transactions' => fn($q) => $q->latest('trx_date')->limit(10)]);
        return view('members.show', compact('member'));
    }

    public function create(): View
    {
        return view('members.form', ['member' => new Member(['branch' => 'Pusat', 'status' => 'Aktif', 'joined_at' => now()])]);
    }

    public function store(Request $request): RedirectResponse
    {
        // Simpanan Pokok (setoran awal) dikirim berformat rupiah ("500.000"): ambil angkanya saja.
        $request->merge(['simpanan_pokok' => preg_replace('/\D/', '', (string) $request->input('simpanan_pokok'))]);
        $pokok = (float) ($request->validate([
            'simpanan_pokok' => ['nullable', 'numeric', 'min:1', 'max:1000000000', Rule::prohibitedIf($request->input('status') !== 'Aktif')],
        ], [
            'numeric' => 'Simpanan pokok harus berupa angka.',
            'min' => 'Simpanan pokok minimal Rp 1.',
            'max' => 'Simpanan pokok terlalu besar (maksimal Rp 1.000.000.000).',
            'prohibited' => 'Simpanan pokok hanya bisa dibayar oleh anggota berstatus Aktif.',
        ])['simpanan_pokok'] ?? 0);
        $data = $this->validated($request);
        // shortcut: nomor = max+1; dua simpan di detik yang sama bisa bentrok (kolom unik menolak salah satunya), upgrade bila ramai.
        $data['member_no'] = 'AG' . str_pad((int) substr(Member::max('member_no') ?? 'AG000000', 2) + 1, 6, '0', STR_PAD_LEFT);
        // Tiga rekening simpanan dibuka dengan saldo 0, pola nomor sama dengan data awal (SAV-0136-1).
        $member = DB::transaction(function () use ($data, $pokok) {
            $member = Member::create($data);
            $digits = str_pad(ltrim(substr($member->member_no, 2), '0'), 4, '0', STR_PAD_LEFT);
            foreach (['Pokok', 'Wajib', 'Sukarela'] as $i => $type) {
                $member->savings()->create(['account_no' => "SAV-{$digits}-" . ($i + 1), 'type' => $type]);
            }
            // Aturan umum: Simpanan Pokok dibayar sekali saat masuk; kas bertambah (D), simpanan pokok bertambah (K).
            if ($pokok > 0) {
                $account = $member->savings()->where('type', 'Pokok')->first();
                $account->increment('balance', $pokok);
                Ledger::post(
                    ['member_id' => $member->id, 'saving_id' => $account->id, 'category' => 'Simpanan', 'direction' => 'in', 'amount' => $pokok, 'description' => "Setoran simpanan Pokok {$member->member_no}"],
                    [[...Ledger::KAS, $pokok, 0], [...Saving::ACCOUNTS['Pokok'], 0, $pokok]]
                );
            }
            return $member;
        });
        $extra = $pokok > 0 ? ' Simpanan Pokok Rp ' . number_format($pokok, 0, ',', '.') . ' tercatat.' : '';
        return redirect()->route('members.show', $member)->with('success', "Anggota {$member->member_no} berhasil ditambahkan.$extra");
    }

    public function edit(Member $member): View
    {
        return view('members.form', compact('member'));
    }

    public function update(Request $request, Member $member): RedirectResponse
    {
        $member->update($this->validated($request, $member));
        return redirect()->route('members.show', $member)->with('success', 'Data anggota berhasil diperbarui.');
    }

    private function validated(Request $request, ?Member $member = null): array
    {
        // NIK: ambil angkanya saja (boleh diketik dengan spasi/titik); kosong harus null, bukan "", karena kolomnya unik.
        $request->merge(['nik' => preg_replace('/\D/', '', (string) $request->input('nik')) ?: null]);
        $phone = ['nullable', 'regex:/^(\+62|62|0)8[0-9]{7,12}$/'];

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'nik' => ['nullable', 'digits:16', Rule::unique('members', 'nik')->ignore($member)],
            'gender' => ['nullable', Rule::in(array_keys(Member::GENDERS))],
            'birth_place' => ['nullable', 'string', 'max:60'],
            'birth_date' => ['nullable', 'date', 'after:1900-01-01', 'before_or_equal:' . now()->subYears(17)->toDateString()],
            'marital_status' => ['nullable', Rule::in(Member::MARITAL)],
            'occupation' => ['nullable', 'string', 'max:80'],
            'phone' => $phone,
            'email' => ['nullable', 'email', 'max:255', Rule::unique('members', 'email')->ignore($member)],
            'address' => ['nullable', 'string', 'max:500'],
            'heir_name' => ['nullable', 'string', 'max:100'],
            'heir_relation' => ['required_with:heir_name', 'nullable', Rule::in(Member::HEIR_RELATIONS)],
            'heir_phone' => $phone,
            'branch' => ['required', Rule::in(self::BRANCHES)],
            'joined_at' => ['required', 'date', 'before_or_equal:today'],
            'status' => ['required', Rule::in(self::STATUSES)],
        ], [
            'required' => ':Attribute wajib diisi.',
            'required_with' => ':Attribute wajib diisi bila nama ahli waris diisi.',
            'in' => ':Attribute tidak valid.',
            'max' => ':Attribute terlalu panjang (maksimal :max karakter).',
            'email' => 'Format email tidak valid.',
            'date' => 'Format tanggal tidak valid.',
            'digits' => 'NIK harus 16 angka.',
            'nik.unique' => 'NIK ini sudah terdaftar pada anggota lain.',
            'phone.regex' => 'Nomor HP harus nomor Indonesia, contoh 081234567890.',
            'heir_phone.regex' => 'Nomor HP ahli waris harus nomor Indonesia, contoh 081234567890.',
            'email.unique' => 'Email ini sudah dipakai anggota lain.',
            'birth_date.before_or_equal' => 'Anggota minimal berusia 17 tahun.',
            'birth_date.after' => 'Tanggal lahir tidak masuk akal.',
            'joined_at.before_or_equal' => 'Tanggal bergabung tidak boleh di masa depan.',
        ], [
            'name' => 'nama',
            'phone' => 'nomor HP',
            'address' => 'alamat',
            'branch' => 'cabang',
            'joined_at' => 'tanggal bergabung',
            'gender' => 'jenis kelamin',
            'birth_place' => 'tempat lahir',
            'birth_date' => 'tanggal lahir',
            'marital_status' => 'status perkawinan',
            'occupation' => 'pekerjaan',
            'heir_name' => 'nama ahli waris',
            'heir_relation' => 'hubungan ahli waris'
        ]);

        // Nama ahli waris dikosongkan = hubungan dan HP-nya ikut dikosongkan, agar tidak tersisa data yatim di profil dan formulir cetak.
        if (array_key_exists('heir_name', $data) && blank($data['heir_name'])) {
            $data['heir_relation'] = $data['heir_phone'] = null;
        }

        return $data;
    }

    // Formulir keanggotaan A4 untuk dicetak dan ditandatangani.
    public function sheet(Member $member): View
    {
        $pokok = (float) $member->savings()->where('type', 'Pokok')->sum('balance');
        return view('members.sheet', compact('member', 'pokok'));
    }

    // Membuat akun portal anggota, atau mengatur ulang kata sandinya. Kata sandi acak hanya tampil sekali, untuk diberikan ke anggota.
    public function portalAccount(Member $member): RedirectResponse
    {
        abort_unless($member->status === 'Aktif', 422, 'Hanya anggota aktif yang bisa memiliki akun portal.');
        $password = Str::password(8, symbols: false);
        User::updateOrCreate(['member_id' => $member->id], ['name' => $member->name, 'email' => strtolower($member->member_no) . '@anggota.local', 'role' => 'anggota', 'password' => $password]);

        return redirect()->route('members.show', $member)->with('portal_login', ['no' => $member->member_no, 'password' => $password]);
    }
}
