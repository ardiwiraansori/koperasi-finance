<?php
namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function index(): View
    {
        return view('settings.index', ['users' => User::where('role', '!=', 'anggota')->orderBy('role')->orderBy('name')->get(), 'roles' => $this->roles()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'koperasi_name' => ['required', 'string', 'max:80'],
            'has_accountant' => ['required', 'in:0,1'],
        ], ['required' => ':Attribute wajib diisi.', 'max' => ':Attribute terlalu panjang (maksimal :max karakter).', 'in' => 'Pilihan mode akuntansi tidak valid.'],
            ['koperasi_name' => 'nama koperasi', 'has_accountant' => 'mode akuntansi']);

        Setting::put('koperasi_name', $data['koperasi_name']);
        Setting::put('has_accountant', $data['has_accountant']);
        return back()->with('success', 'Pengaturan disimpan.');
    }

    public function storeUser(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'max:100'],
            'role' => ['required', Rule::in(array_keys($this->roles()))],
        ], $this->messages(), ['name' => 'nama', 'email' => 'email', 'password' => 'sandi', 'role' => 'peran']);

        User::create($data);
        return back()->with('success', "Pengguna {$data['name']} ditambahkan sebagai ".User::ROLES[$data['role']].'.');
    }

    public function updateUser(Request $request, User $user): RedirectResponse
    {
        // Peran akuntan tetap diizinkan bagi yang sudah menjabat, walau mode akuntan dimatikan.
        $allowed = array_keys($this->roles()) + ($user->role === 'akuntan' ? [99 => 'akuntan'] : []);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8', 'max:100'],
            'role' => ['required', Rule::in($allowed)],
        ], $this->messages(), ['name' => 'nama', 'email' => 'email', 'password' => 'sandi', 'role' => 'peran']);

        if ($data['role'] !== $user->role) {
            if ($user->id === $request->user()->id) {
                throw ValidationException::withMessages(['role' => 'Anda tidak bisa mengubah peran akun Anda sendiri.']);
            }
            if ($user->role === 'admin' && User::where('role', 'admin')->count() <= 1) {
                throw ValidationException::withMessages(['role' => 'Harus ada minimal satu admin.']);
            }
        }

        $user->fill(array_filter($data, fn ($v, $k) => $k !== 'password' || filled($v), ARRAY_FILTER_USE_BOTH))->save();
        return back()->with('success', "Pengguna {$user->name} diperbarui.");
    }

    // Pengguna yang berhenti dihapus: sesi yang masih terbuka ikut mati pada permintaan berikutnya karena akunnya tidak ada lagi
    // (mengganti sandi saja tidak menutup sesi atau cookie "ingat saya" yang sudah ada). Tidak ada data lain yang menunjuk ke pengguna.
    public function destroyUser(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            return back()->with('error', 'Anda tidak bisa menghapus akun Anda sendiri.');
        }
        if ($user->role === 'admin' && User::where('role', 'admin')->count() <= 1) {
            return back()->with('error', 'Harus ada minimal satu admin.');
        }

        $user->delete();
        return back()->with('success', "Pengguna {$user->name} dihapus. Sesinya tidak berlaku lagi.");
    }

    // Peran yang boleh dipilih: akuntan hanya bila koperasi memiliki akuntan.
    private function roles(): array
    {
        return collect(User::ROLES)->when(! Setting::hasAccountant(), fn ($r) => $r->except('akuntan'))->all();
    }

    private function messages(): array
    {
        return [
            'required' => ':Attribute wajib diisi.', 'email' => 'Format email tidak valid.', 'unique' => 'Email ini sudah dipakai pengguna lain.',
            'min' => ':Attribute minimal :min karakter.', 'max' => ':Attribute terlalu panjang.', 'in' => ':Attribute tidak valid.',
        ];
    }
}
