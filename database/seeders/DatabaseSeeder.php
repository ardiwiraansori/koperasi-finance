<?php

namespace Database\Seeders;

use App\Models\JournalEntry;
use App\Models\Loan;
use App\Models\Member;
use App\Models\Saving;
use App\Models\Transaction;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $members = collect([
                ['AG000128','Budi Santoso','081234567801','budi@example.test','Jl. Merdeka No. 12','Pusat','2021-01-12','Aktif'],
                ['AG000129','Rina Aprillia','081234567802','rina@example.test','Jl. Anggrek No. 8','Pusat','2022-03-18','Aktif'],
                ['AG000130','Andi Saputra','081234567803','andi@example.test','Jl. Melati No. 4','Cabang A','2020-07-05','Aktif'],
                ['AG000131','Siti Rahma','081234567804','siti@example.test','Jl. Veteran No. 33','Cabang A','2023-02-20','Aktif'],
                ['AG000132','Fajar Nugraha','081234567805','fajar@example.test','Jl. Sudirman No. 91','Pusat','2019-11-11','Aktif'],
                ['AG000133','Dewi Lestari','081234567806','dewi@example.test','Jl. Mawar No. 15','Cabang B','2024-04-02','Aktif'],
                ['AG000134','Rizki Maulana','081234567807','rizki@example.test','Jl. Diponegoro No. 51','Cabang B','2022-09-14','Aktif'],
                ['AG000135','Nadia Putri','081234567808','nadia@example.test','Jl. Karya No. 17','Pusat','2025-01-21','Aktif'],
            ])->map(fn ($m) => Member::create([
                'member_no'=>$m[0], 'name'=>$m[1], 'phone'=>$m[2], 'email'=>$m[3], 'address'=>$m[4], 'branch'=>$m[5], 'joined_at'=>$m[6], 'status'=>$m[7]
            ]));

            $balances = [
                [500000,4550000,7500000], [500000,3600000,4000000], [500000,6200000,13500000], [500000,2900000,1800000],
                [500000,8100000,16200000], [500000,2100000,3500000], [500000,4300000,6300000], [500000,1250000,950000]
            ];
            foreach ($members as $i => $member) {
                foreach (['Pokok','Wajib','Sukarela'] as $j => $type) {
                    Saving::create([
                        'member_id'=>$member->id,
                        'account_no'=>'SAV-'.str_pad((string)($i+128), 4, '0', STR_PAD_LEFT).'-'.($j+1),
                        'type'=>$type,
                        'balance'=>$balances[$i][$j],
                    ]);
                }
            }

            $loanRows = [
                [0,'PIN-2026-000182','Pinjaman Reguler',25000000,12,24,1176837,8250000,'Aktif','2025-10-20','2026-09-25'],
                [2,'PIN-2026-000190','Pinjaman Usaha',40000000,11,36,1309500,25000000,'Tunggakan','2025-12-02','2026-08-25'],
                [4,'PIN-2026-000197','Pinjaman Reguler',18000000,12,18,1098000,10980000,'Aktif','2026-02-11','2026-09-28'],
                [6,'PIN-2026-000203','Pinjaman Pendidikan',15000000,9,12,1312000,15000000,'Menunggu Approval','2026-09-19',null],
                [1,'PIN-2025-000151','Pinjaman Reguler',12000000,12,12,1066000,0,'Lunas','2025-01-15',null],
            ];
            foreach ($loanRows as $l) {
                Loan::create([
                    'member_id'=>$members[$l[0]]->id,'loan_no'=>$l[1],'product'=>$l[2],'principal'=>$l[3],'interest_rate'=>$l[4],
                    'term_months'=>$l[5],'installment'=>$l[6],'outstanding'=>$l[7],'status'=>$l[8],'applied_at'=>$l[9],'next_due_at'=>$l[10]
                ]);
            }

            $trx = [
                ['TRX-260921001',0,'Simpanan','in',500000,'Setoran simpanan wajib','2026-09-21 09:14:00'],
                ['TRX-260921002',2,'Angsuran','in',1309500,'Pembayaran angsuran pinjaman','2026-09-21 09:28:00'],
                ['TRX-260921003',1,'Simpanan','in',250000,'Setoran simpanan sukarela','2026-09-21 10:03:00'],
                ['TRX-260921004',5,'Simpanan','out',300000,'Penarikan simpanan sukarela','2026-09-21 10:24:00'],
                ['TRX-260920005',4,'Angsuran','in',1098000,'Pembayaran angsuran pinjaman','2026-09-20 13:12:00'],
                ['TRX-260920006',3,'Simpanan','in',500000,'Setoran simpanan wajib','2026-09-20 14:33:00'],
                ['TRX-260919007',0,'Angsuran','in',1176837,'Pembayaran angsuran pinjaman','2026-09-19 11:45:00'],
                ['TRX-260919008',7,'Simpanan','in',200000,'Setoran simpanan sukarela','2026-09-19 15:09:00'],
                ['TRX-260918009',null,'Penerimaan','in',2750000,'Pendapatan administrasi dan jasa','2026-09-18 08:50:00'],
                ['TRX-260918010',null,'Pengeluaran','out',1250000,'Biaya operasional kantor','2026-09-18 16:10:00'],
            ];
            foreach ($trx as $t) {
                Transaction::create([
                    'trx_no'=>$t[0],'member_id'=>$t[1] === null ? null : $members[$t[1]]->id,'category'=>$t[2],'direction'=>$t[3],
                    'amount'=>$t[4],'description'=>$t[5],'trx_date'=>$t[6],'status'=>'Posted'
                ]);
            }

            $journals = [
                ['JU-260900123','2026-09-21','TRX-260921002','Pembayaran angsuran PIN-2026-000190','AUTO JOURNAL',[
                    ['10101','Kas Teller',1309500,0],['11301','Piutang Pinjaman',0,1100000],['41101','Pendapatan Bunga',0,209500],
                ]],
                ['JU-260900122','2026-09-21','TRX-260921001','Setoran simpanan wajib AG000128','AUTO JOURNAL',[
                    ['10101','Kas Teller',500000,0],['20202','Simpanan Wajib Anggota',0,500000],
                ]],
                ['JU-260900121','2026-09-20','TRX-260920006','Setoran simpanan wajib AG000131','AUTO JOURNAL',[
                    ['10101','Kas Teller',500000,0],['20202','Simpanan Wajib Anggota',0,500000],
                ]],
                ['JU-260900120','2026-09-18','TRX-260918010','Biaya operasional kantor','AUTO JOURNAL',[
                    ['51101','Beban Operasional',1250000,0],['10101','Kas Teller',0,1250000],
                ]],
            ];
            foreach ($journals as $j) {
                $entry = JournalEntry::create(['journal_no'=>$j[0],'journal_date'=>$j[1],'reference'=>$j[2],'description'=>$j[3],'source'=>$j[4],'status'=>'Posted']);
                foreach ($j[5] as $line) {
                    $entry->lines()->create(['account_code'=>$line[0],'account_name'=>$line[1],'debit'=>$line[2],'credit'=>$line[3]]);
                }
            }
        });
    }
}
