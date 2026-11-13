# Form anggota lengkap dan formulir keanggotaan cetak

Status: ditulis dari permintaan pengguna ("buat form untuk masing-masing anggota"), dijalankan tanpa menunggu persetujuan
karena pengguna menetapkan alurnya (analisis, bangun, review, perbaiki). Poin bertanda **[asumsi]** adalah usulan saya.

## Maksud
Pengurus dan kasir mencatat anggota baru dengan data yang lazim di formulir keanggotaan koperasi di Indonesia, lalu mencetak
formulir bertanda tangan untuk arsip. Form dasar saat ini hanya nama, HP, email, alamat, cabang, status, tanggal bergabung.

## Tujuan
- Form tambah/ubah anggota lengkap, nyaman di HP (kasir input di Android) dan di desktop.
- Formulir keanggotaan A4 per anggota: siap cetak dan tanda tangan.
- Profil anggota menampilkan data baru.

## Bukan tujuan (**[asumsi]**)
- Unggah foto/KTP: berkas identitas di hosting bersama menambah risiko privasi dan penyimpanan; ditunda.
- Formulir kosong (untuk diisi tangan): bisa menyusul bila diminta.
- Wizard bertahap: satu halaman berbagian sudah cukup untuk ±12 kolom.

## Anti-tujuan
- Memaksa semua kolom wajib: kasir tidak boleh macet bila calon anggota belum membawa KTP. Hanya nama, cabang, status, dan tanggal bergabung wajib (seperti sekarang).
- NIK ganda: satu NIK tidak boleh dipakai dua anggota.

## Pendekatan yang dipertimbangkan
1. **Kolom baru di tabel `members`** (dipilih): ±9 kolom opsional; sederhana, mudah dicari, dicetak, dan diekspor.
2. Tabel profil terpisah `member_profiles`: lebih rapi secara teori, tetapi menambah join dan kode untuk data yang selalu dibaca bersama anggota.
3. Kolom JSON: fleksibel, tetapi tidak bisa unik (NIK) dan sulit divalidasi/dicari.

## Isi form (**[asumsi]** mengikuti formulir umum koperasi)
- **Data pribadi:** nama lengkap*, NIK (16 digit, unik), jenis kelamin, tempat dan tanggal lahir (minimal 17 tahun), status perkawinan, pekerjaan.
- **Kontak dan alamat:** HP, email, alamat.
- **Ahli waris:** nama, hubungan, HP (hubungan wajib bila nama diisi).
- **Keanggotaan:** cabang*, status*, tanggal bergabung*, simpanan pokok awal (hanya saat tambah).

## Tampilan
- Satu halaman, empat bagian berjudul (fieldset + legend), satu kolom di HP dan dua kolom di desktop.
- Pilihan kecil (jenis kelamin) berupa tombol pilihan besar, bukan dropdown, supaya satu ketukan.
- Tombol Simpan/Batal menempel di bawah layar HP agar tidak perlu menggulir.
- Galat tampil di bawah kolomnya; kolom opsional ditandai "opsional".

## Formulir cetak
Halaman `/anggota/{id}/formulir`: kop (nama koperasi), judul, nomor anggota, bagian A–D berisi data,
pernyataan kesediaan mematuhi AD/ART, dan dua kotak tanda tangan (anggota dan pengurus). Tombol Cetak di profil.
Semua peran boleh membuka (sama seperti profil).

## Diserahkan ke pembangun
Nama kolom, urutan bagian, teks pernyataan persisnya, gaya cetak.
