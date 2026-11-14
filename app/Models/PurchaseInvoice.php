<?php
/**
 * Model Purchase Invoice (Faktur Pembelian)
 * Modul Finance & Akuntansi Terintegrasi WMS
 *
 * Mengelola Faktur Pembelian:
 * 1. Tipe STOK (Auto-generate dari Surat Jalan Penerimaan Barang: Dr. 1200 Persediaan / Cr. 2000 Utang)
 * 2. Tipe OPERASIONAL (Input Manual Biaya Operasional / Non-Stok: Dr. 6000 Biaya Operasional / Cr. 2000 Utang)
 * 3. Tipe ASET (Pembelian Aset / Peralatan: Dr. 1100 Aset Lainnya / Cr. 2000 Utang)
 */

namespace App\Models;

use App\Core\Database;
use PDO;
use Exception;

class PurchaseInvoice {

    /**
     * Mengambil daftar Purchase Invoice dengan filter dan paginasi
     */
    public static function getAll(array $filters = [], int $limit = 50, int $offset = 0): array {
        $pdo = Database::getConnection();

        $where = ["1=1"];
        $params = [];

        if (($filters['tipe_invoice'] ?? '') === 'NON_STOK') {
            $where[] = "pi.tipe_invoice != 'STOK'";
        } elseif (!empty($filters['tipe_invoice']) && $filters['tipe_invoice'] !== 'SEMUA') {
            $where[] = "pi.tipe_invoice = :tipe_invoice";
            $params[':tipe_invoice'] = $filters['tipe_invoice'];
        }

        if (!empty($filters['status_bayar']) && $filters['status_bayar'] !== 'SEMUA') {
            if ($filters['status_bayar'] === 'BELUM_LUNAS') {
                $where[] = "pi.status_bayar NOT IN ('LUNAS','BATAL')";
            } else {
                $where[] = "pi.status_bayar = :status_bayar";
                $params[':status_bayar'] = $filters['status_bayar'];
            }
        }

        if (!empty($filters['pemasok_id'])) {
            $where[] = "pi.pemasok_id = :pemasok_id";
            $params[':pemasok_id'] = (int)$filters['pemasok_id'];
        }

        if (!empty($filters['search'])) {
            $s = '%' . trim($filters['search']) . '%';
            $where[] = "(pi.no_invoice LIKE :s OR pi.nama_vendor LIKE :s OR pi.no_surat_jalan LIKE :s OR pi.keterangan LIKE :s)";
            $params[':s'] = $s;
        }

        if (!empty($filters['start_date'])) {
            $where[] = "pi.tanggal_invoice >= :start_date";
            $params[':start_date'] = $filters['start_date'];
        }

        if (!empty($filters['end_date'])) {
            $where[] = "pi.tanggal_invoice <= :end_date";
            $params[':end_date'] = $filters['end_date'];
        }

        $whereSql = implode(' AND ', $where);

        $sql = "
            SELECT 
                pi.*,
                p.kode_pemasok,
                DATEDIFF(CURRENT_DATE, pi.tanggal_invoice) AS umur_hari,
                DATEDIFF(pi.tanggal_jatuh_tempo, CURRENT_DATE) AS sisa_hari_jatuh_tempo,
                (SELECT COUNT(id) FROM purchase_invoice_items WHERE invoice_id = pi.id) AS total_item_count
            FROM purchase_invoices pi
            LEFT JOIN pemasok p ON pi.pemasok_id = p.id
            WHERE {$whereSql}
            ORDER BY pi.tanggal_invoice DESC, pi.id DESC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Hitung total invoice berdasarkan filter
     */
    public static function countAll(array $filters = []): int {
        $pdo = Database::getConnection();

        $where = ["1=1"];
        $params = [];

        if (($filters['tipe_invoice'] ?? '') === 'NON_STOK') {
            $where[] = "tipe_invoice != 'STOK'";
        } elseif (!empty($filters['tipe_invoice']) && $filters['tipe_invoice'] !== 'SEMUA') {
            $where[] = "tipe_invoice = :tipe_invoice";
            $params[':tipe_invoice'] = $filters['tipe_invoice'];
        }

        if (!empty($filters['status_bayar']) && $filters['status_bayar'] !== 'SEMUA') {
            if ($filters['status_bayar'] === 'BELUM_LUNAS') {
                $where[] = "status_bayar NOT IN ('LUNAS','BATAL')";
            } else {
                $where[] = "status_bayar = :status_bayar";
                $params[':status_bayar'] = $filters['status_bayar'];
            }
        }

        if (!empty($filters['pemasok_id'])) {
            $where[] = "pemasok_id = :pemasok_id";
            $params[':pemasok_id'] = (int)$filters['pemasok_id'];
        }

        if (!empty($filters['search'])) {
            $s = '%' . trim($filters['search']) . '%';
            $where[] = "(no_invoice LIKE :s OR nama_vendor LIKE :s OR no_surat_jalan LIKE :s OR keterangan LIKE :s)";
            $params[':s'] = $s;
        }

        if (!empty($filters['start_date'])) {
            $where[] = "tanggal_invoice >= :start_date";
            $params[':start_date'] = $filters['start_date'];
        }

        if (!empty($filters['end_date'])) {
            $where[] = "tanggal_invoice <= :end_date";
            $params[':end_date'] = $filters['end_date'];
        }

        $whereSql = implode(' AND ', $where);
        $stmt = $pdo->prepare("SELECT COUNT(id) FROM purchase_invoices WHERE {$whereSql}");
        $stmt->execute($params);

        return (int)$stmt->fetchColumn();
    }

    /**
     * Ambil 1 Invoice beserta Rincian Item (Line Items)
     */
    public static function findById(int $id): ?array {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            SELECT 
                pi.*,
                p.kode_pemasok,
                p.telepon AS vendor_telepon,
                p.alamat AS vendor_alamat,
                DATEDIFF(CURRENT_DATE, pi.tanggal_invoice) AS umur_hari,
                DATEDIFF(pi.tanggal_jatuh_tempo, CURRENT_DATE) AS sisa_hari_jatuh_tempo
            FROM purchase_invoices pi
            LEFT JOIN pemasok p ON pi.pemasok_id = p.id
            WHERE pi.id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$invoice) return null;

        $stmtItems = $pdo->prepare("
            SELECT pii.*, it.kode_item
            FROM purchase_invoice_items pii
            LEFT JOIN items it ON pii.item_id = it.id
            WHERE pii.invoice_id = :id
            ORDER BY pii.id ASC
        ");
        $stmtItems->execute([':id' => $id]);
        $invoice['items'] = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

        // Ambil riwayat pembayaran invoice ini
        $noSj = trim($invoice['no_surat_jalan'] ?? '');
        if ($noSj !== '') {
            $stmtPay = $pdo->prepare("
                SELECT 
                    aap.nominal_alokasi,
                    aap.nominal_asal,
                    aap.sisa_utang_setelah,
                    ap.id AS pembayaran_id,
                    ap.status AS status_pembayaran,
                    ap.alasan_batal,
                    ap.jumlah_asal,
                    ap.jumlah_bayar AS total_pembayaran,
                    ap.no_bukti_keluar,
                    ap.tanggal_bayar,
                    ap.metode_bayar,
                    ap.bank_sumber
                FROM ap_alokasi_pembayaran aap
                JOIN ap_pembayaran ap ON aap.pembayaran_id = ap.id
                WHERE aap.invoice_id = :id OR aap.no_surat_jalan = :no_sj
                ORDER BY ap.tanggal_bayar DESC, aap.id DESC
            ");
            $stmtPay->execute([':id' => $id, ':no_sj' => $noSj]);
        } else {
            $stmtPay = $pdo->prepare("
                SELECT 
                    aap.nominal_alokasi,
                    aap.nominal_asal,
                    aap.sisa_utang_setelah,
                    ap.id AS pembayaran_id,
                    ap.status AS status_pembayaran,
                    ap.alasan_batal,
                    ap.jumlah_asal,
                    ap.jumlah_bayar AS total_pembayaran,
                    ap.no_bukti_keluar,
                    ap.tanggal_bayar,
                    ap.metode_bayar,
                    ap.bank_sumber
                FROM ap_alokasi_pembayaran aap
                JOIN ap_pembayaran ap ON aap.pembayaran_id = ap.id
                WHERE aap.invoice_id = :id
                ORDER BY ap.tanggal_bayar DESC, aap.id DESC
            ");
            $stmtPay->execute([':id' => $id]);
        }
        $invoice['payments'] = $stmtPay->fetchAll(PDO::FETCH_ASSOC);

        return $invoice;
    }

    /**
     * Cari Invoice berdasarkan nomor Surat Jalan
     */
    public static function findBySuratJalan(string $noSj): ?array {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM purchase_invoices WHERE no_surat_jalan = :sj LIMIT 1");
        $stmt->execute([':sj' => trim($noSj)]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Generate Nomor Invoice Otomatis
     * Contoh: PI-2611-0001 (Stok) atau OPR-2611-0001 (Operasional)
     */
    public static function generateNextInvoiceNumber(string $tipe = 'OPERASIONAL'): string {
        $pdo = Database::getConnection();
        $ym = date('ym');
        $prefix = ($tipe === 'STOK') ? 'PI-STK-' . $ym : (($tipe === 'ASET') ? 'PI-AST-' . $ym : 'PI-OPR-' . $ym);

        $stmt = $pdo->prepare("
            SELECT no_invoice 
            FROM purchase_invoices 
            WHERE no_invoice LIKE :prefix 
            ORDER BY id DESC 
            LIMIT 1
        ");
        $stmt->execute([':prefix' => $prefix . '-%']);
        $lastNo = $stmt->fetchColumn();

        $seq = 1;
        if ($lastNo) {
            $parts = explode('-', $lastNo);
            $lastSeq = (int)end($parts);
            $seq = $lastSeq + 1;
        }

        return $prefix . '-' . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Buat Purchase Invoice Manual (untuk Biaya Operasional / Non-Stok)
     */
    public static function createManual(array $data): int {
        $pdo = Database::getConnection();
        $pdo->beginTransaction();

        try {
            $tipe = $data['tipe_invoice'] ?? 'OPERASIONAL';
            $noInvoice = !empty($data['no_invoice']) ? trim($data['no_invoice']) : self::generateNextInvoiceNumber($tipe);

            $tglInvoice = !empty($data['tanggal_invoice']) ? $data['tanggal_invoice'] : date('Y-m-d');
            \App\Services\PeriodeService::pastikanTerbuka($tglInvoice, 'mencatat faktur');
            $tglJatuhTempo = !empty($data['tanggal_jatuh_tempo']) ? $data['tanggal_jatuh_tempo'] : date('Y-m-d', strtotime('+14 days'));

            // Akun debit: dipilih dari master COA; tanpa pilihan, ikuti tipe lama
            if (!empty($data['kode_akun_debit']) && $tipe !== 'STOK') {
                $akunRow = \App\Services\CoaService::akunFakturValid(trim((string)$data['kode_akun_debit']));
                $kodeAkunDebit = $akunRow['kode'];
                $namaAkunDebit = $akunRow['nama'];
                $tipe = $akunRow['tipe'] === 'ASSET' ? 'ASET' : 'OPERASIONAL';
            } elseif ($tipe === 'OPERASIONAL') {
                $kodeAkunDebit = '6000';
                $namaAkunDebit = 'Biaya Operasional';
            } elseif ($tipe === 'ASET') {
                $kodeAkunDebit = '1100';
                $namaAkunDebit = 'Piutang & Aset Lainnya';
            } else {
                $kodeAkunDebit = $data['kode_akun_debit'] ?? '1200';
                $namaAkunDebit = $data['nama_akun_debit'] ?? 'Inventory / Persediaan';
            }

            $items = $data['items'] ?? [];
            $subtotal = 0.0;
            foreach ($items as $it) {
                $q = max(0.01, (float)($it['qty'] ?? 1));
                $p = max(0, (float)($it['harga_satuan'] ?? 0));
                $subtotal += ($q * $p);
            }

            if ($subtotal <= 0 && !empty($data['total_nominal'])) {
                $subtotal = (float)$data['total_nominal'];
            }

            $pajakPpn = (float)($data['pajak_ppn'] ?? 0);
            $totalNominal = $subtotal + $pajakPpn;
            $sisaUtang = $totalNominal;

            $stmt = $pdo->prepare("
                INSERT INTO purchase_invoices (
                    no_invoice, tipe_invoice, pemasok_id, nama_vendor, no_surat_jalan,
                    tanggal_invoice, tanggal_jatuh_tempo,
                    kode_akun_debit, nama_akun_debit, kode_akun_kredit, nama_akun_kredit,
                    subtotal, pajak_ppn, total_nominal, total_bayar, sisa_utang,
                    status_bayar, keterangan, created_by
                ) VALUES (
                    :no_invoice, :tipe_invoice, :pemasok_id, :nama_vendor, :no_surat_jalan,
                    :tanggal_invoice, :tanggal_jatuh_tempo,
                    :kode_akun_debit, :nama_akun_debit, '2000', 'Utang',
                    :subtotal, :pajak_ppn, :total_nominal, 0.00, :sisa_utang,
                    'BELUM_BAYAR', :keterangan, :created_by
                )
            ");
            $stmt->execute([
                ':no_invoice'          => $noInvoice,
                ':tipe_invoice'        => $tipe,
                ':pemasok_id'          => !empty($data['pemasok_id']) ? (int)$data['pemasok_id'] : null,
                ':nama_vendor'         => trim($data['nama_vendor'] ?? 'Vendor Operasional'),
                ':no_surat_jalan'      => !empty($data['no_surat_jalan']) ? trim($data['no_surat_jalan']) : null,
                ':tanggal_invoice'     => $tglInvoice,
                ':tanggal_jatuh_tempo' => $tglJatuhTempo,
                ':kode_akun_debit'     => $kodeAkunDebit,
                ':nama_akun_debit'     => $namaAkunDebit,
                ':subtotal'            => $subtotal,
                ':pajak_ppn'           => $pajakPpn,
                ':total_nominal'       => $totalNominal,
                ':sisa_utang'          => $sisaUtang,
                ':keterangan'          => $data['keterangan'] ?? null,
                ':created_by'          => $data['created_by'] ?? null
            ]);

            $invoiceId = (int)$pdo->lastInsertId();

            // Insert line items
            if (!empty($items)) {
                $stmtItem = $pdo->prepare("
                    INSERT INTO purchase_invoice_items (
                        invoice_id, item_id, deskripsi, qty, satuan, harga_satuan, subtotal
                    ) VALUES (
                        :invoice_id, :item_id, :deskripsi, :qty, :satuan, :harga_satuan, :subtotal
                    )
                ");
                foreach ($items as $it) {
                    $q = max(0.01, (float)($it['qty'] ?? 1));
                    $p = max(0, (float)($it['harga_satuan'] ?? 0));
                    $sub = round($q * $p, 2);
                    $stmtItem->execute([
                        ':invoice_id'    => $invoiceId,
                        ':item_id'       => !empty($it['item_id']) ? (int)$it['item_id'] : null,
                        ':deskripsi'     => trim($it['deskripsi'] ?? 'Biaya Operasional'),
                        ':qty'           => $q,
                        ':satuan'        => trim($it['satuan'] ?? 'Pcs'),
                        ':harga_satuan'  => $p,
                        ':subtotal'      => $sub
                    ]);
                }
            } else {
                // Default 1 line item
                $stmtItem = $pdo->prepare("
                    INSERT INTO purchase_invoice_items (
                        invoice_id, deskripsi, qty, satuan, harga_satuan, subtotal
                    ) VALUES (
                        :invoice_id, :deskripsi, 1.00, 'Pcs', :harga_satuan, :subtotal
                    )
                ");
                $stmtItem->execute([
                    ':invoice_id'   => $invoiceId,
                    ':deskripsi'    => trim($data['nama_vendor'] ?? 'Biaya Operasional'),
                    ':harga_satuan' => $subtotal,
                    ':subtotal'     => $subtotal
                ]);
            }

            \App\Services\JurnalService::postPurchaseInvoice((int)$invoiceId);

            $pdo->commit();
            return $invoiceId;

        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * AUTO-GENERATE Purchase Invoice dari Surat Jalan Penerimaan Barang (STOK)
     * Dipanggil otomatis saat "Selesaikan & Cetak Surat Jalan" di views/timbang_cetak.php
     */
    public static function createFromSuratJalan(string $noSj, int $pemasokId = 0): ?int {
        $pdo = Database::getConnection();
        $noSj = trim($noSj);
        if (empty($noSj)) return null;

        // Cek data barang di labels untuk Surat Jalan ini
        $stmtLabels = $pdo->prepare("
            SELECT 
                l.item_id,
                it.nama_item,
                it.kode_item,
                jb.nama_jenis,
                COUNT(l.id) AS total_box,
                SUM(l.berat_netto) AS total_netto_kg,
                l.harga_beli_per_kg AS harga_beli_kg
            FROM labels l
            JOIN items it ON l.item_id = it.id
            LEFT JOIN jenis_barang jb ON it.jenis_id = jb.id
            WHERE l.no_surat_jalan = :sj
            GROUP BY l.item_id, it.nama_item, it.kode_item, jb.nama_jenis, l.harga_beli_per_kg
        ");
        $stmtLabels->execute([':sj' => $noSj]);
        $rows = $stmtLabels->fetchAll(PDO::FETCH_ASSOC);

        if (empty($rows)) return null;

        // Ambil info pemasok dan tanggal surat jalan
        $stmtMeta = $pdo->prepare("
            SELECT 
                l.pemasok_id,
                p.nama_pemasok,
                p.kode_pemasok,
                MIN(l.created_at) AS tgl_masuk
            FROM labels l
            JOIN pemasok p ON l.pemasok_id = p.id
            WHERE l.no_surat_jalan = :sj
            GROUP BY l.pemasok_id, p.nama_pemasok, p.kode_pemasok
            LIMIT 1
        ");
        $stmtMeta->execute([':sj' => $noSj]);
        $meta = $stmtMeta->fetch(PDO::FETCH_ASSOC);

        $finalPemasokId = $meta ? (int)$meta['pemasok_id'] : $pemasokId;
        $namaVendor = $meta['nama_pemasok'] ?? 'Pemasok Barang';
        $tglInvoice = $meta && !empty($meta['tgl_masuk']) ? date('Y-m-d', strtotime($meta['tgl_masuk'])) : date('Y-m-d');
        $tglJatuhTempo = date('Y-m-d', strtotime($tglInvoice . ' +7 days'));

        $subtotal = 0.0;
        $lineItems = [];
        foreach ($rows as $r) {
            $kg = (float)$r['total_netto_kg'];
            $harga = (float)$r['harga_beli_kg'];
            $sub = round($kg * $harga, 2);
            $subtotal += $sub;

            $lineItems[] = [
                'item_id'      => (int)$r['item_id'],
                'deskripsi'    => $r['nama_item'] . ' (' . $r['total_box'] . ' Box)',
                'qty'          => $kg,
                'satuan'       => 'KG',
                'harga_satuan' => $harga,
                'subtotal'     => $sub
            ];
        }

        // Cek total bayar yang sudah teralokasi di ap_alokasi_pembayaran
        $stmtBayar = $pdo->prepare("
            SELECT COALESCE(SUM(nominal_alokasi), 0)
            FROM ap_alokasi_pembayaran
            WHERE no_surat_jalan = :sj
        ");
        $stmtBayar->execute([':sj' => $noSj]);
        $totalBayar = (float)$stmtBayar->fetchColumn();

        $sisaUtang = max(0, round($subtotal - $totalBayar, 2));
        $statusBayar = ($sisaUtang <= 0.01) ? 'LUNAS' : (($totalBayar > 0) ? 'SEBAGIAN' : 'BELUM_BAYAR');

        // Cek apakah invoice untuk surat jalan ini sudah ada
        $existing = self::findBySuratJalan($noSj);
        if ($existing) {
            // Faktur otomatis sudah terbit: nominal dikunci, tidak dihitung ulang dari stok
            $pdo->prepare("UPDATE ap_alokasi_pembayaran SET invoice_id = :i WHERE no_surat_jalan = :sj AND (invoice_id IS NULL OR invoice_id = 0)")->execute([':i' => (int)$existing['id'], ':sj' => $noSj]);
            return (int)$existing['id'];
        }

        $pdo->beginTransaction();
        try {
            if ($existing) {
                $invoiceId = (int)$existing['id'];
                $stmtUp = $pdo->prepare("
                    UPDATE purchase_invoices SET
                        pemasok_id = :pemasok_id,
                        nama_vendor = :nama_vendor,
                        tanggal_invoice = :tgl_invoice,
                        subtotal = :subtotal,
                        total_nominal = :total_nominal,
                        total_bayar = :total_bayar,
                        sisa_utang = :sisa_utang,
                        status_bayar = :status_bayar,
                        keterangan = :keterangan
                    WHERE id = :id
                ");
                $stmtUp->execute([
                    ':pemasok_id'    => $finalPemasokId,
                    ':nama_vendor'   => $namaVendor,
                    ':tgl_invoice'   => $tglInvoice,
                    ':subtotal'      => $subtotal,
                    ':total_nominal' => $subtotal,
                    ':total_bayar'   => $totalBayar,
                    ':sisa_utang'    => $sisaUtang,
                    ':status_bayar'  => $statusBayar,
                    ':keterangan'    => 'Penerimaan otomatis Surat Jalan ' . $noSj,
                    ':id'            => $invoiceId
                ]);

                // Hapus dan perbarui line items
                $pdo->prepare("DELETE FROM purchase_invoice_items WHERE invoice_id = :id")->execute([':id' => $invoiceId]);
            } else {
                // Generate nomor invoice resmi stok (INV-SJ-XXXX)
                $noInvoice = 'INV-' . $noSj;

                $stmtIns = $pdo->prepare("
                    INSERT INTO purchase_invoices (
                        no_invoice, tipe_invoice, pemasok_id, nama_vendor, no_surat_jalan,
                        tanggal_invoice, tanggal_jatuh_tempo,
                        kode_akun_debit, nama_akun_debit, kode_akun_kredit, nama_akun_kredit,
                        subtotal, pajak_ppn, total_nominal, total_bayar, sisa_utang,
                        status_bayar, keterangan
                    ) VALUES (
                        :no_invoice, 'STOK', :pemasok_id, :nama_vendor, :no_surat_jalan,
                        :tanggal_invoice, :tanggal_jatuh_tempo,
                        '1200', 'Inventory / Persediaan', '2000', 'Utang',
                        :subtotal, 0.00, :total_nominal, :total_bayar, :sisa_utang,
                        :status_bayar, :keterangan
                    )
                ");
                $stmtIns->execute([
                    ':no_invoice'          => $noInvoice,
                    ':pemasok_id'          => $finalPemasokId,
                    ':nama_vendor'         => $namaVendor,
                    ':no_surat_jalan'      => $noSj,
                    ':tanggal_invoice'     => $tglInvoice,
                    ':tanggal_jatuh_tempo' => $tglJatuhTempo,
                    ':subtotal'            => $subtotal,
                    ':total_nominal'       => $subtotal,
                    ':total_bayar'         => $totalBayar,
                    ':sisa_utang'          => $sisaUtang,
                    ':status_bayar'        => $statusBayar,
                    ':keterangan'          => 'Penerimaan otomatis Surat Jalan ' . $noSj
                ]);
                $invoiceId = (int)$pdo->lastInsertId();
            }

            // Insert line items
            $stmtItem = $pdo->prepare("
                INSERT INTO purchase_invoice_items (
                    invoice_id, item_id, deskripsi, qty, satuan, harga_satuan, subtotal
                ) VALUES (
                    :invoice_id, :item_id, :deskripsi, :qty, 'KG', :harga_satuan, :subtotal
                )
            ");
            foreach ($lineItems as $it) {
                $stmtItem->execute([
                    ':invoice_id'    => $invoiceId,
                    ':item_id'       => $it['item_id'],
                    ':deskripsi'     => $it['deskripsi'],
                    ':qty'           => $it['qty'],
                    ':harga_satuan'  => $it['harga_satuan'],
                    ':subtotal'      => $it['subtotal']
                ]);
            }

            // Sync invoice_id di ap_alokasi_pembayaran jika ada
            $pdo->prepare("
                UPDATE ap_alokasi_pembayaran 
                SET invoice_id = :invoice_id 
                WHERE no_surat_jalan = :sj AND (invoice_id IS NULL OR invoice_id = 0)
            ")->execute([':invoice_id' => $invoiceId, ':sj' => $noSj]);

            \App\Services\JurnalService::postPurchaseInvoice((int)$invoiceId);

            $pdo->commit();
            return $invoiceId;

        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Sinkronisasi Backfill Seluruh Surat Jalan Historis ke Purchase Invoices
     */
    public static function syncAllSuratJalan(): int {
        $pdo = Database::getConnection();
        $stmt = $pdo->query("
            SELECT DISTINCT no_surat_jalan, pemasok_id 
            FROM labels 
            WHERE no_surat_jalan IS NOT NULL AND no_surat_jalan != ''
        ");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $count = 0;
        foreach ($rows as $r) {
            $res = self::createFromSuratJalan($r['no_surat_jalan'], (int)$r['pemasok_id']);
            if ($res) $count++;
        }

        return $count;
    }

    /**
     * Sinkronisasi Total Pembayaran dan Sisa Utang Invoice Tertentu
     */
    public static function syncPaymentStatus(int $invoiceId): void {
        $pdo = Database::getConnection();
        $inv = self::findById($invoiceId);
        if (!$inv || $inv['status_bayar'] === 'BATAL') return;

        $noSj = trim($inv['no_surat_jalan'] ?? '');
        if ($noSj !== '') {
            $stmtSum = $pdo->prepare("
                SELECT COALESCE(SUM(nominal_alokasi), 0)
                FROM ap_alokasi_pembayaran
                WHERE invoice_id = :id OR no_surat_jalan = :sj
            ");
            $stmtSum->execute([':id' => $invoiceId, ':sj' => $noSj]);
        } else {
            $stmtSum = $pdo->prepare("
                SELECT COALESCE(SUM(nominal_alokasi), 0)
                FROM ap_alokasi_pembayaran
                WHERE invoice_id = :id
            ");
            $stmtSum->execute([':id' => $invoiceId]);
        }

        $totalBayar = (float)$stmtSum->fetchColumn();
        $sisaUtang = max(0, round((float)$inv['total_nominal'] - $totalBayar, 2));
        $statusBayar = ($sisaUtang <= 0.01) ? 'LUNAS' : (($totalBayar > 0) ? 'SEBAGIAN' : 'BELUM_BAYAR');

        $pdo->prepare("
            UPDATE purchase_invoices 
            SET total_bayar = :bayar, sisa_utang = :sisa, status_bayar = :st 
            WHERE id = :id
        ")->execute([
            ':bayar' => $totalBayar,
            ':sisa'  => $sisaUtang,
            ':st'    => $statusBayar,
            ':id'    => $invoiceId
        ]);
    }

    /**
     * Rekap Statistik Finansial Purchase Invoice untuk Dashboard KPI
     */
    public static function getSummaryKpi(): array {
        $pdo = Database::getConnection();

        $sql = "
            SELECT 
                COUNT(id) AS total_invoice,
                COALESCE(SUM(total_nominal), 0) AS total_nominal,
                COALESCE(SUM(total_bayar), 0) AS total_bayar,
                COALESCE(SUM(sisa_utang), 0) AS sisa_utang,
                COALESCE(SUM(CASE WHEN tipe_invoice = 'STOK' THEN total_nominal ELSE 0 END), 0) AS total_stok,
                COALESCE(SUM(CASE WHEN tipe_invoice != 'STOK' THEN total_nominal ELSE 0 END), 0) AS total_operasional,
                COALESCE(SUM(CASE WHEN tipe_invoice != 'STOK' THEN sisa_utang ELSE 0 END), 0) AS sisa_operasional,
                COUNT(CASE WHEN status_bayar = 'LUNAS' THEN 1 END) AS count_lunas,
                COUNT(CASE WHEN status_bayar NOT IN ('LUNAS','BATAL') THEN 1 END) AS count_belum_lunas
            FROM purchase_invoices
            WHERE status_bayar <> 'BATAL'
        ";
        $row = $pdo->query($sql)->fetch(PDO::FETCH_ASSOC);

        return [
            'total_invoice'     => (int)($row['total_invoice'] ?? 0),
            'total_nominal'     => (float)($row['total_nominal'] ?? 0),
            'total_bayar'       => (float)($row['total_bayar'] ?? 0),
            'sisa_utang'        => (float)($row['sisa_utang'] ?? 0),
            'total_stok'        => (float)($row['total_stok'] ?? 0),
            'total_operasional' => (float)($row['total_operasional'] ?? 0),
            'sisa_operasional'  => (float)($row['sisa_operasional'] ?? 0),
            'count_lunas'       => (int)($row['count_lunas'] ?? 0),
            'count_belum_lunas' => (int)($row['count_belum_lunas'] ?? 0)
        ];
    }
}
