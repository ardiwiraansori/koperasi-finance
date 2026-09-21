# Koperasi One — Demo Keuangan Koperasi

Demo web app koperasi berbasis **Laravel 13 + MySQL + Blade**. UI dibuat desktop-first untuk operator/admin, tetapi tetap responsive.

## Modul demo

- Dashboard eksekutif
- Anggota + Member 360°
- Simpanan
- Pinjaman
- Kasir / Teller (form tersambung ke tabel transaksi)
- Akuntansi / Jurnal Umum
- Pusat Laporan
- Approval
- Simulasi SHU
- Pengaturan

> **Catatan:** ini adalah demo fondasi UI + data model, belum aplikasi koperasi production. Update saldo, perhitungan bunga, angsuran, auto-journal, approval matrix, closing, dan rumus SHU harus mengikuti aturan bisnis koperasi yang sebenarnya.

---

## Stack yang direkomendasikan di Windows 10

- Laravel Herd
- PHP **8.3–8.5** (disarankan PHP 8.4 / 8.5)
- Composer dari Herd
- MySQL Server (port default 3306)
- DBeaver sebagai database client
- VS Code

Frontend demo tidak memakai npm/Vite, sehingga **npm install tidak diperlukan** untuk menjalankan UI ini.

---

## 1. Extract folder

Extract sehingga struktur akhirnya:

```text
C:\Users\ASUS\Herd\koperasi-finance
    app\
    bootstrap\
    config\
    database\
    public\
    resources\
    routes\
    storage\
    artisan
    composer.json
```

Jangan sampai menjadi folder ganda seperti:

```text
C:\Users\ASUS\Herd\koperasi-finance\koperasi-finance\artisan
```

---

## 2. Cek PHP dan Composer Herd

Buka PowerShell / terminal VS Code:

```powershell
cd C:\Users\ASUS\Herd\koperasi-finance
php -v
composer -V
```

Pastikan PHP minimal 8.3.

---

## 3. Install dependency Laravel

```powershell
composer install
```

Karena folder `vendor` sengaja tidak dimasukkan ke ZIP, langkah ini wajib sekali setelah extract.

---

## 4. Buat `.env`

PowerShell:

```powershell
Copy-Item .env.example .env
php artisan key:generate
```

Isi database pada `.env` bila username/password MySQL Anda berbeda:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=koperasi_demo
DB_USERNAME=root
DB_PASSWORD=
```

`APP_URL` sudah diarahkan ke:

```env
APP_URL=http://koperasi-finance.test
```

---

## 5. Buat database melalui DBeaver

Hubungkan DBeaver ke MySQL Anda, lalu jalankan:

```sql
CREATE DATABASE IF NOT EXISTS koperasi_demo
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
```

SQL yang sama juga ada di:

```text
database\create_database.sql
```

> DBeaver hanyalah database client. Pastikan service **MySQL Server** benar-benar sedang berjalan.

---

## 6. Migration + data demo

```powershell
php artisan migrate:fresh --seed
php artisan optimize:clear
```

Seeder akan membuat data anggota, simpanan, pinjaman, transaksi, dan jurnal contoh.

---

## 7. Buka dari Herd

Karena `C:\Users\ASUS\Herd` adalah parked directory Herd, buka:

```text
http://koperasi-finance.test
```

Atau dari terminal project:

```powershell
herd open
```

Tidak perlu menjalankan `php artisan serve` bila Anda menggunakan parked site Herd.

---

## Shortcut setup

Setelah database `koperasi_demo` dibuat dan `.env` sudah benar, Anda juga dapat menjalankan:

```text
setup-demo.bat
```

Script akan memasang dependency, memastikan APP_KEY ada, menjalankan migration + seeder, lalu membersihkan cache.

---

## Setting VS Code yang berguna

Extension opsional:

- PHP Intelephense
- Laravel Extra Intellisense / Laravel Extension
- Blade formatter
- EditorConfig

Buka **folder `koperasi-finance` langsung sebagai workspace**, bukan folder `Herd` seluruhnya.

---

## Hal yang perlu dibuat sebelum production

1. Login, user, role, permission.
2. Approval matrix berjenjang.
3. Master produk simpanan / pinjaman.
4. Engine bunga flat, sliding, anuitas.
5. Jadwal angsuran dan denda.
6. Posting transaksi ke jurnal otomatis.
7. Ledger / buku besar / trial balance.
8. Lock & closing periode akuntansi.
9. Perhitungan SHU sesuai AD/ART dan RAT.
10. Audit trail dan void/reversal transaksi.
11. Nomor dokumen yang concurrency-safe.
12. Backup, restore, hardening, HTTPS, dan test otomatis.

## Akun demo

Belum ada login pada versi ini agar demo UI bisa langsung dibuka setelah migrate. Sidebar menampilkan identitas dummy **Admin Demo**.
