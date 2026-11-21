# Koperasi Finance

Web application untuk pengelolaan keuangan koperasi berbasis Laravel.

Aplikasi dirancang untuk menangani proses utama koperasi seperti:

- Keanggotaan
- Simpanan
- Pinjaman
- Kasir / Teller
- Kas & Bank
- Akuntansi
- Jurnal Umum
- Approval
- SHU
- Laporan Keuangan
- Audit Trail

> Project saat ini masih dalam tahap pengembangan / prototype.

---

# Technology Stack

Project menggunakan:

- Laravel 13
- PHP >= 8.3
- MySQL / MariaDB
- Blade Template
- HTML / CSS / JavaScript
- Composer
- Laravel Herd
- Git / GitHub

Environment development utama:

- Windows 10
- Laravel Herd
- Visual Studio Code
- MySQL
- DBeaver
- GitHub Desktop

---

# Pemeriksaan manual dialog (JavaScript)

Tes otomatis hanya memeriksa penanda dialog di halaman, bukan perilaku JavaScript-nya. Setelah mengubah `public/assets/app.js`,
coba sekali di browser (muat ulang paksa dengan Ctrl+Shift+R):

1. **Tolak pengajuan** (Approval): dialog muncul; menekan Tolak dengan alasan kosong menampilkan "Alasan wajib diisi." dan tidak mengirim; alasan terisi lalu Tolak berhasil.
2. **Cairkan** (menu Pencairan): dialog muncul dengan fokus di Batal; Esc dan Batal tidak mencairkan; Cairkan mencairkan satu kali (tombol tidak bisa ditekan dua kali).
3. **Hapus pengguna** (Pengaturan) dan **Atur ulang kata sandi portal** (profil anggota): dialog muncul, Batal tidak mengubah apa pun.
4. **Pengaman**: buka Console (F12), jalankan `window.dialogReady = false`, tekan Cairkan: tidak ada yang terkirim (form ber-dialog tidak boleh jalan tanpa dialog).

---

# Repository

```text
https://github.com/ardiwiraansori/koperasi-finance