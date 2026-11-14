<?php
namespace App\Services;

use App\Core\Database;
use Exception;
use PDO;

/**
 * Pembatalan dokumen. Dokumen tidak dihapus: statusnya berubah dan jurnalnya dibalik,
 * sehingga stok, piutang, utang, dan COA ikut kembali.
 */
class PembatalanService
{
    private static function audit(int $userId, string $aksi, string $tabel, $targetId, string $alasan): void
    {
        $db = Database::getConnection();
        $db->prepare("INSERT INTO audit_logs (user_id, action_name, target_table, target_id, reason, ip_address) VALUES (?,?,?,?,?,?)")
           ->execute([$userId, $aksi, $tabel, (string)$targetId, mb_substr($alasan, 0, 255), $_SERVER['REMOTE_ADDR'] ?? 'cli']);
    }

    private static function wajibAlasan(string $alasan): string
    {
        $alasan = trim($alasan);
        if (mb_strlen($alasan) < 3) {
            throw new Exception('Alasan pembatalan wajib diisi.');
        }
        return $alasan;
    }

    /** Batalkan Pengeluaran ke mitra: barang kembali ke gudang, nota dan jurnal dibalik. */
    public static function batalPengeluaran(int $transaksiId, string $alasan, int $userId): array
    {
        $alasan = self::wajibAlasan($alasan);
        $db = Database::getConnection();

        $st = $db->prepare("SELECT * FROM transaksi_stok WHERE id = ?");
        $st->execute([$transaksiId]);
        $t = $st->fetch(PDO::FETCH_ASSOC);
        if (!$t) throw new Exception('Surat jalan keluar tidak ditemukan.');
        if ($t['jenis_transaksi'] === 'PENGELUARAN_BATAL') throw new Exception('Surat jalan keluar ini sudah dibatalkan.');
        if ($t['jenis_transaksi'] !== 'PENGELUARAN') throw new Exception('Dokumen ini bukan pengeluaran ke mitra.');
        PeriodeService::pastikanTerbuka(substr((string)$t['created_at'], 0, 10), 'membatalkan dokumen ini');

        $bayar = $db->prepare("SELECT COUNT(*) FROM ar_alokasi_pembayaran WHERE transaksi_stok_id = ? AND nominal_alokasi > 0");
        $bayar->execute([$transaksiId]);
        if ((int)$bayar->fetchColumn() > 0) {
            throw new Exception("Nota {$t['no_transaksi']} sudah memiliki setoran dari mitra, jadi tidak dapat dibatalkan.");
        }

        $q = $db->prepare("SELECT COUNT(*) FROM accurate_sync_queue WHERE transaksi_id = ? AND status = 'SUCCESS'");
        $q->execute([$transaksiId]);
        if ((int)$q->fetchColumn() > 0) {
            throw new Exception("Surat jalan keluar ini sudah terkirim ke Accurate dan tidak dapat dibatalkan dari sini.");
        }

        $db->beginTransaction();
        try {
            // Label kembali ke gudang dengan biaya beli semula (harga_per_kg sempat tertimpa harga jual)
            $db->prepare("
                UPDATE labels l
                JOIN transaksi_stok_detail d ON d.label_id = l.id
                SET l.status = 'AKTIF',
                    l.mitra_id = NULL,
                    l.harga_per_kg = IF(l.harga_beli_per_kg > 0, l.harga_beli_per_kg, l.harga_per_kg),
                    l.total_nilai = ROUND(l.berat_netto * IF(l.harga_beli_per_kg > 0, l.harga_beli_per_kg, l.harga_per_kg), 2),
                    l.updated_at = CURRENT_TIMESTAMP
                WHERE d.transaksi_id = ? AND l.status = 'KELUAR'
            ")->execute([$transaksiId]);

            // Dokumen ditandai batal; keluar dari semua laporan yang memfilter jenis PENGELUARAN
            $db->prepare("UPDATE transaksi_stok SET jenis_transaksi = 'PENGELUARAN_BATAL', alasan_batal = ?, dibatalkan_at = NOW(), dibatalkan_oleh = ? WHERE id = ?")
               ->execute([$alasan, $userId, $transaksiId]);

            // Antrean Accurate yang belum terkirim dibatalkan
            $db->prepare("UPDATE accurate_sync_queue SET status = 'FAILED', error_message = 'Dibatalkan: surat jalan keluar dibatalkan' WHERE transaksi_id = ? AND status IN ('PENDING','PROCESSING','FAILED')")
               ->execute([$transaksiId]);

            JurnalService::reverse('PENGELUARAN', $transaksiId, $alasan);
            self::audit($userId, 'BATAL_PENGELUARAN', 'transaksi_stok', $transaksiId, $alasan);
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
        return ['no_transaksi' => $t['no_transaksi']];
    }

    /** Batalkan Penerimaan (satu surat jalan): keranjang di-VOID, faktur dan jurnal dibalik. */
    public static function batalPenerimaan(string $noSj, string $alasan, int $userId): array
    {
        $alasan = self::wajibAlasan($alasan);
        $noSj = trim($noSj);
        if ($noSj === '') throw new Exception('Nomor surat jalan tidak valid.');
        $db = Database::getConnection();

        $st = $db->prepare("SELECT id, status FROM labels WHERE no_surat_jalan = ?");
        $st->execute([$noSj]);
        $labels = $st->fetchAll(PDO::FETCH_ASSOC);
        if (!$labels) throw new Exception("Surat jalan {$noSj} tidak ditemukan.");

        $aktif = array_filter($labels, fn($l) => $l['status'] === 'AKTIF');
        $void  = array_filter($labels, fn($l) => $l['status'] === 'VOID');
        if (count($void) === count($labels)) throw new Exception("Surat jalan {$noSj} sudah dibatalkan.");
        if (count($aktif) + count($void) !== count($labels)) {
            throw new Exception("Surat jalan {$noSj} tidak dapat dibatalkan karena sebagian keranjangnya sudah dikeluarkan ke mitra. Batalkan pengeluarannya terlebih dahulu.");
        }

        $inv = $db->prepare("SELECT * FROM purchase_invoices WHERE no_surat_jalan = ? AND tipe_invoice = 'STOK' LIMIT 1");
        $inv->execute([$noSj]);
        $faktur = $inv->fetch(PDO::FETCH_ASSOC);
        if ($faktur) PeriodeService::pastikanTerbuka((string)$faktur['tanggal_invoice'], 'membatalkan penerimaan ini');
        if ($faktur && (float)$faktur['total_bayar'] > 0) {
            throw new Exception("Surat jalan {$noSj} sudah dibayar ke pemasok (faktur {$faktur['no_invoice']}), jadi tidak dapat dibatalkan.");
        }
        $alok = $db->prepare("SELECT COUNT(*) FROM ap_alokasi_pembayaran WHERE no_surat_jalan = ? AND nominal_alokasi > 0");
        $alok->execute([$noSj]);
        if ((int)$alok->fetchColumn() > 0) {
            throw new Exception("Surat jalan {$noSj} sudah memiliki pembayaran ke pemasok, jadi tidak dapat dibatalkan.");
        }

        $db->beginTransaction();
        try {
            $db->prepare("UPDATE labels SET status = 'VOID', alasan_void = ?, updated_at = CURRENT_TIMESTAMP WHERE no_surat_jalan = ? AND status = 'AKTIF'")
               ->execute([mb_substr('Penerimaan dibatalkan: ' . $alasan, 0, 255), $noSj]);

            if ($faktur) {
                $db->prepare("UPDATE purchase_invoices SET status_bayar = 'BATAL', sisa_utang = 0, alasan_batal = ?, dibatalkan_at = NOW() WHERE id = ?")
                   ->execute([$alasan, $faktur['id']]);
                JurnalService::reverse('PURCHASE_INV', (int)$faktur['id'], $alasan);
            }
            self::audit($userId, 'BATAL_PENERIMAAN', 'labels', $noSj, $alasan);
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
        return ['no_surat_jalan' => $noSj, 'jumlah_keranjang' => count($aktif)];
    }

    /** Batalkan faktur manual (non-penerimaan): status Batal dan jurnal dibalik. */
    public static function batalPurchaseInvoice(int $invoiceId, string $alasan, int $userId): array
    {
        $alasan = self::wajibAlasan($alasan);
        $db = Database::getConnection();

        $st = $db->prepare("SELECT * FROM purchase_invoices WHERE id = ?");
        $st->execute([$invoiceId]);
        $inv = $st->fetch(PDO::FETCH_ASSOC);
        if (!$inv) throw new Exception('Faktur tidak ditemukan.');
        if ($inv['status_bayar'] === 'BATAL') throw new Exception("Faktur {$inv['no_invoice']} sudah dibatalkan.");
        PeriodeService::pastikanTerbuka((string)$inv['tanggal_invoice'], 'membatalkan faktur ini');
        if ($inv['tipe_invoice'] === 'STOK') {
            throw new Exception("Faktur {$inv['no_invoice']} berasal dari Penerimaan. Batalkan dari Riwayat Penerimaan.");
        }
        if ((float)$inv['total_bayar'] > 0) {
            throw new Exception("Faktur {$inv['no_invoice']} sudah memiliki pembayaran, jadi tidak dapat dibatalkan.");
        }

        $db->beginTransaction();
        try {
            $db->prepare("UPDATE purchase_invoices SET status_bayar = 'BATAL', sisa_utang = 0, alasan_batal = ?, dibatalkan_at = NOW() WHERE id = ?")
               ->execute([$alasan, $invoiceId]);
            JurnalService::reverse('PURCHASE_INV', $invoiceId, $alasan);
            self::audit($userId, 'BATAL_FAKTUR', 'purchase_invoices', $invoiceId, $alasan);
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
        return ['no_invoice' => $inv['no_invoice']];
    }

    /** Batalkan transaksi kas: status menjadi BATAL dan jurnalnya dibalik. */
    public static function batalKas(int $kasId, string $alasan, int $userId): array
    {
        $alasan = self::wajibAlasan($alasan);
        KasService::ensureTable();
        $db = Database::getConnection();
        $st = $db->prepare("SELECT * FROM kas_transaksi WHERE id = ?");
        $st->execute([$kasId]);
        $k = $st->fetch(PDO::FETCH_ASSOC);
        if (!$k) throw new Exception('Transaksi kas tidak ditemukan.');
        if ($k['status'] === 'BATAL') throw new Exception("Transaksi {$k['no_bukti']} sudah dibatalkan.");
        PeriodeService::pastikanTerbuka((string)$k['tanggal'], 'membatalkan transaksi kas ini');

        $db->beginTransaction();
        try {
            $db->prepare("UPDATE kas_transaksi SET status = 'BATAL', alasan_batal = ?, dibatalkan_at = NOW(), dibatalkan_oleh = ? WHERE id = ?")
               ->execute([$alasan, $userId, $kasId]);
            JurnalService::reverse('KAS', $kasId, $alasan);
            self::audit($userId, 'BATAL_KAS', 'kas_transaksi', $kasId, $alasan);
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
        return ['no_bukti' => $k['no_bukti'], 'nominal' => (float)$k['nominal']];
    }

    /** Batalkan pembayaran ke pemasok: faktur dan surat jalan kembali terutang, kas dan utang dikoreksi. */
    public static function batalPembayaranAp(int $pembayaranId, string $alasan, int $userId): array
    {
        $alasan = self::wajibAlasan($alasan);
        $db = Database::getConnection();

        $st = $db->prepare("SELECT * FROM ap_pembayaran WHERE id = ?");
        $st->execute([$pembayaranId]);
        $p = $st->fetch(PDO::FETCH_ASSOC);
        if (!$p) throw new Exception('Pembayaran tidak ditemukan.');
        if ($p['status'] === 'BATAL') throw new Exception("Pembayaran {$p['no_bukti_keluar']} sudah dibatalkan.");
        PeriodeService::pastikanTerbuka((string)$p['tanggal_bayar'], 'membatalkan pembayaran ini');

        $al = $db->prepare("SELECT DISTINCT invoice_id, no_surat_jalan FROM ap_alokasi_pembayaran WHERE pembayaran_id = ?");
        $al->execute([$pembayaranId]);
        $alokasi = $al->fetchAll(PDO::FETCH_ASSOC);

        $db->beginTransaction();
        try {
            $db->prepare("UPDATE ap_alokasi_pembayaran SET nominal_asal = nominal_alokasi, nominal_alokasi = 0 WHERE pembayaran_id = ?")->execute([$pembayaranId]);
            $db->prepare("UPDATE ap_pembayaran SET status = 'BATAL', jumlah_asal = jumlah_bayar, jumlah_bayar = 0, alasan_batal = ?, dibatalkan_at = NOW(), dibatalkan_oleh = ? WHERE id = ?")
               ->execute([$alasan, $userId, $pembayaranId]);

            // Status faktur dihitung ulang dari alokasi yang masih aktif
            $sudah = [];
            foreach ($alokasi as $a) {
                $invId = (int)($a['invoice_id'] ?? 0);
                if ($invId <= 0 && !empty($a['no_surat_jalan'])) {
                    $inv = \App\Models\PurchaseInvoice::findBySuratJalan($a['no_surat_jalan']);
                    $invId = $inv ? (int)$inv['id'] : 0;
                }
                if ($invId > 0 && empty($sudah[$invId])) {
                    \App\Models\PurchaseInvoice::syncPaymentStatus($invId);
                    $sudah[$invId] = true;
                }
            }

            JurnalService::reverse('AP_BAYAR', $pembayaranId, $alasan);
            self::audit($userId, 'BATAL_BAYAR_AP', 'ap_pembayaran', $pembayaranId, $alasan);
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
        return ['no_bukti' => $p['no_bukti_keluar'], 'jumlah' => (float)$p['jumlah_bayar']];
    }

    /** Batalkan setoran mitra: nota kembali terutang, kas dan piutang dikoreksi. */
    public static function batalSetoranAr(int $pembayaranId, string $alasan, int $userId): array
    {
        $alasan = self::wajibAlasan($alasan);
        $db = Database::getConnection();

        $st = $db->prepare("SELECT * FROM ar_pembayaran WHERE id = ?");
        $st->execute([$pembayaranId]);
        $p = $st->fetch(PDO::FETCH_ASSOC);
        if (!$p) throw new Exception('Setoran tidak ditemukan.');
        if ($p['status'] === 'BATAL') throw new Exception("Setoran {$p['no_bukti_bayar']} sudah dibatalkan.");
        PeriodeService::pastikanTerbuka((string)$p['tanggal_bayar'], 'membatalkan setoran ini');
        if (($p['status_rekonsiliasi'] ?? 'PENDING') !== 'PENDING') {
            throw new Exception("Setoran {$p['no_bukti_bayar']} tanggal {$p['tanggal_bayar']} sudah masuk Rekonsiliasi Bank, jadi tidak dapat dibatalkan. Buka kembali rekonsiliasi tanggal itu dulu.");
        }

        $db->beginTransaction();
        try {
            $db->prepare("UPDATE ar_alokasi_pembayaran SET nominal_asal = nominal_alokasi, nominal_alokasi = 0 WHERE pembayaran_id = ?")->execute([$pembayaranId]);
            $db->prepare("UPDATE ar_pembayaran SET status = 'BATAL', jumlah_asal = jumlah_bayar, jumlah_bayar = 0, alasan_batal = ?, dibatalkan_at = NOW(), dibatalkan_oleh = ? WHERE id = ?")
               ->execute([$alasan, $userId, $pembayaranId]);
            JurnalService::reverse('AR_SETOR', $pembayaranId, $alasan);
            self::audit($userId, 'BATAL_SETORAN_AR', 'ar_pembayaran', $pembayaranId, $alasan);
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
        return ['no_bukti' => $p['no_bukti_bayar'], 'jumlah' => (float)$p['jumlah_bayar']];
    }
}
