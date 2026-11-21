# Desain: Top-up Pinjaman (pelunasan pinjaman lama dari pencairan baru)

Status: disetujui dan sudah diimplementasikan (2026-11-22).

## Tujuan dan alasan

Kebijakan koperasi: anggota yang masih punya pinjaman berjalan boleh mengajukan pinjaman baru, tetapi pinjaman lama **wajib dilunasi dari pencairan pinjaman baru**. Contoh: plafon baru Rp 10 juta, kewajiban lama Rp 3 juta, uang yang keluar dari kas Rp 7 juta.

Hari ini aplikasi tidak mendukung ini. Pengajuan ditolak untuk anggota yang menunggak, dan anggota yang pinjamannya masih lancar bisa mengajukan lagi tanpa melunasi apa pun, sehingga satu anggota bisa punya beberapa pinjaman berjalan.

**Sasaran:** satu anggota paling banyak punya satu pinjaman berjalan; pelunasan terjadi otomatis, tercatat benar di jurnal, dan aman dipakai banyak petugas bersamaan.
**Bukan sasaran:** denda atau biaya administrasi (keputusan: belum), pembayaran sebagian, penjadwalan ulang pinjaman lama, SHU.
**Gagal bila:** pinjaman lama dilunasi dua kali, kas minus, jurnal tidak seimbang, atau anggota berakhir dengan dua pinjaman berjalan.

## Aturan (sudah diputuskan koperasi)

1. Kasir mengajukan; admin menyetujui dan mencairkan (sudah ada di Kelompok 2).
2. Anggota dengan pinjaman Aktif atau Tunggakan boleh mengajukan. Pelunasan wajib, tanpa pilihan.
3. Kewajiban lama = sisa pokok + bunga, dihitung ulang **saat pencairan**. Bunga sebulan = `round(sisa pokok x bunga / 1200)` (sama dengan kasir). Pinjaman lancar membayar bunga 1 bulan; pinjaman yang menunggak membayar bunga sebulan dikali **jumlah jatuh tempo yang sudah lewat** (terlambat 95 hari = 4 bulan), karena bunga bulan yang terlewat tidak tercatat di tempat lain. (Ditambahkan setelah review, 2026-11-22; angsuran biasa di kasir tetap sebulan.)
4. Plafon baru harus **lebih besar** dari seluruh kewajiban lama. Kalau tidak, ditolak.
5. Anggota yang menunggak boleh mengajukan lewat top-up. Layar persetujuan menampilkan status tunggakan dan hari terlambat.
6. Bila anggota punya lebih dari satu pinjaman berjalan (dari saldo awal), semuanya dilunasi sekaligus.
7. Tanpa denda dan biaya administrasi.
8. Bunga dan cicilan pinjaman baru dihitung dari **plafon penuh** (12,5% per tahun, anuitas), bukan dari uang yang diterima.

## Pendekatan yang dipertimbangkan

- **A. Jurnal kotor (dipilih).** Setiap pinjaman lama dilunasi dengan jurnal angsuran sendiri (Kas D / Piutang K pokok / Pendapatan Bunga K bunga), lalu pinjaman baru dicairkan seperti biasa (Piutang D / Kas K plafon). Uang yang keluar dari kas sama dengan selisihnya. Sejarah pinjaman lama punya catatan pelunasan, dan Arus Kas menampilkan pencairan serta pelunasan apa adanya. Memakai ulang aturan yang sudah ada.
- **B. Satu jurnal bersih.** Satu jurnal berisi piutang baru D, piutang lama K, bunga K, kas K selisih. Lebih sedikit baris, tetapi sejarah pinjaman lama tidak punya kejadian pelunasan dan Arus Kas hanya memperlihatkan selisihnya.
- **C. Pelunasan manual di kasir sebelum pencairan.** Ditolak koperasi: anggota harus mengeluarkan uang dulu.

## Rancangan

**Data.** Satu kolom baru di pinjaman: `settled_by_loan_id` (kosong atau menunjuk pinjaman baru yang melunasinya). Tidak ada tabel baru. Pinjaman baru dikenali sebagai top-up bila ada pinjaman yang menunjuk kepadanya.

**Perhitungan.** `Loan::interestDue()` (bunga bulan berjalan dari sisa pokok) dan `Loan::payoffQuote(member)` (daftar pinjaman berjalan anggota dengan sisa pokok, bunga, total, hari terlambat). Kasir dan pencairan memakai rumus bunga yang sama, jadi tidak bisa berbeda.

**Pengajuan (`LoanController::store`).**
- Hapus penolakan karena menunggak. Pertahankan: anggota harus Aktif, dan tidak boleh ada pengajuan lain yang menunggu atau disetujui.
- Bila ada pinjaman berjalan: plafon harus lebih besar dari total kewajiban saat ini (pengecekan awal; hasil akhir diputuskan saat pencairan).

**Pencairan (`LoanController::disburse`), satu transaksi database, urutan kunci tetap:**
1. `Ledger::lockCash()` (pernyataan pertama).
2. Kunci baris anggota, lalu pinjaman baru, lalu semua pinjaman berjalan milik anggota urut id naik.
3. Pastikan status baru masih Disetujui dan anggota aktif.
4. Hitung kewajiban lama (di bawah kunci). Plafon harus lebih besar dari totalnya.
5. Cek kas dengan **uang yang benar-benar keluar** (plafon dikurangi total kewajiban lama).
6. Untuk tiap pinjaman lama: jurnal pelunasan (kategori Angsuran, keterangan "Pelunasan lewat top-up PIN-baru"), sisa pokok menjadi 0, status Lunas, jatuh tempo kosong, `settled_by_loan_id` diisi.
7. Cairkan pinjaman baru seperti sekarang: jurnal Pencairan sebesar plafon, status Aktif, jatuh tempo berikutnya sebulan lagi, pencair tercatat.

Tanpa pinjaman berjalan, langkah 2 sampai 6 kosong dan perilakunya sama dengan sekarang.

**Tampilan.**
- Formulir pengajuan (kasir): setelah memilih anggota, tampil panel "Pelunasan pinjaman lama" berisi rincian dan perkiraan uang yang diterima; peringatan bila plafon belum cukup. Datanya dari server, dikirim sebagai data halaman (tanpa permintaan tambahan).
- Pusat Approval dan detail pinjaman: untuk pengajuan top-up tampil rincian pelunasan (nomor, status, hari terlambat, sisa pokok, bunga, total) dan uang yang akan diterima. Setelah dicairkan, pinjaman lama menampilkan "Dilunasi lewat PIN-xxxx".
- Dialog konfirmasi Cairkan menyebut jumlah kas yang keluar dan jumlah yang dilunasi.

## Keputusan saya (bukan dari koperasi; mohon dikoreksi bila salah)

1. Kas dicek dengan uang yang keluar bersih, bukan plafon penuh.
2. Bunga lama dihitung satu bulan penuh (bukan harian), sama dengan aturan kasir sekarang.
3. Pelunasan dicatat sebagai kategori Angsuran supaya muncul di sejarah pinjaman lama tanpa jenis transaksi baru.
4. Pengecekan "plafon lebih besar" juga dilakukan saat pengajuan (pesan cepat bagi kasir), tetapi keputusan akhir saat pencairan.

## Pengujian

Pencairan top-up satu pinjaman (jumlah, jurnal seimbang, kas turun sebesar selisih, pinjaman lama Lunas dan menunjuk yang baru); beberapa pinjaman berjalan; menunggak; plafon sama atau lebih kecil ditolak (di pengajuan dan di pencairan setelah kewajiban naik); kas hanya cukup untuk selisih (lolos) dan kurang dari selisih (ditolak); pelunasan sudah terjadi di antara pengajuan dan pencairan; pengajuan kedua selagi ada yang menunggu ditolak; anggota tanpa pinjaman berjalan tidak berubah; riwayat dan halaman tampil. Kunci tidak bisa diuji di SQLite; urutan kunci dijaga lewat catatan di kode.

## Penerapan

Satu migrasi (`settled_by_loan_id`). Di Render berjalan otomatis; di Rumahweb Small (tanpa SSH) lewat cron sekali jalan sebelum kode baru dipakai.
