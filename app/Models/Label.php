<?php
/**
 * Model Label Barcode Kemasan
 * Menangani pembuatan format ID barcode, kalkulasi catch-weight, validasi status, dan histori.
 */

namespace App\Models;

use PDO;
use Exception;
use App\Core\Database;
use App\Services\BarcodeService;

class Label {
    /**
     * Menghasilkan ID Label Terstruktur: [KodeMitra]-[KodeJenis]-[YYMMDD]-[Urut 5 digit]
     * Contoh: M03-JB02-261022-00045
     */
    public static function generateNextLabelId(string $kodeMitra, string $kodeJenis, ?string $date = null): string {
        $db = Database::getConnection();
        $dateStr = $date ? date('ymd', strtotime($date)) : date('ymd');

        $prefix = sprintf('%s-%s-%s-', strtoupper(trim($kodeMitra)), strtoupper(trim($kodeJenis)), $dateStr);

        // Cari nomor urut terbesar pada hari tersebut (Global Daily Sequence agar anti-duplicate antar pemasok)
        $stmt = $db->prepare("SELECT id_label FROM labels WHERE id_label LIKE ? ORDER BY id DESC LIMIT 1");
        $stmt->execute(['%-' . $dateStr . '-%']);
        $lastRow = $stmt->fetch();

        $nextSeq = 1;
        if ($lastRow && !empty($lastRow['id_label'])) {
            $parts = explode('-', $lastRow['id_label']);
            $lastNum = (int)end($parts);
            $nextSeq = $lastNum + 1;
        }

        // Loop verifikasi untuk menjamin nomor urut hari ini 100% unik global dan tidak pernah double
        do {
            $candidateSeqStr = sprintf('%05d', $nextSeq);
            $stmtCheck = $db->prepare("SELECT COUNT(*) FROM labels WHERE id_label LIKE ?");
            $stmtCheck->execute(['%-' . $dateStr . '-' . $candidateSeqStr]);
            if ((int)$stmtCheck->fetchColumn() > 0) {
                $nextSeq++;
            } else {
                break;
            }
        } while (true);

        return sprintf('%s%05d', $prefix, $nextSeq);
    }

    /**
     * Menghasilkan Nomor Surat Jalan Otomatis: SJ-[KodeMitra]-[YYMMDD]-[Urut 4 digit]
     * Contoh: SJ-M01-261022-0001
     */
    public static function generateNextSuratJalan(string $kodeMitra): string {
        $db = Database::getConnection();
        $dateStr = date('ymd');
        $cleanKode = strtoupper(trim($kodeMitra));
        $prefix = sprintf('SJ-%s-%s-', $cleanKode, $dateStr);

        $stmt = $db->prepare("SELECT no_surat_jalan FROM labels WHERE no_surat_jalan LIKE ? ORDER BY LENGTH(no_surat_jalan) DESC, no_surat_jalan DESC LIMIT 1");
        $stmt->execute([$prefix . '%']);
        $lastRow = $stmt->fetch();

        $nextSeq = 1;
        if ($lastRow && !empty($lastRow['no_surat_jalan'])) {
            $parts = explode('-', $lastRow['no_surat_jalan']);
            $lastNum = (int)end($parts);
            $nextSeq = $lastNum + 1;
        }

        // Loop untuk menjamin nomor Surat Jalan BENAR-BENAR UNIK dan tidak double nantinya
        do {
            $candidate = sprintf('%s%04d', $prefix, $nextSeq);
            $stmtCheck = $db->prepare("SELECT COUNT(*) FROM labels WHERE no_surat_jalan = ?");
            $stmtCheck->execute([$candidate]);
            if ((int)$stmtCheck->fetchColumn() > 0) {
                $nextSeq++;
            } else {
                break;
            }
        } while (true);

        return $candidate;
    }

    /**
     * Buat dan simpan label barcode baru (Stasiun Timbang)
     */
    public static function create(array $data): array {
        $db = Database::getConnection();

        // Ambil data pemasok (prioritas untuk penerimaan) atau mitra
        $pemasokId = !empty($data['pemasok_id']) ? (int)$data['pemasok_id'] : null;
        $pemasok = $pemasokId ? Pemasok::findById($pemasokId) : null;

        $mitraId = !empty($data['mitra_id']) ? (int)$data['mitra_id'] : null;
        $mitra = $mitraId ? Mitra::findById($mitraId) : null;

        $item = Item::findById((int)$data['item_id']);
        if (!$item) throw new Exception("Item tidak ditemukan");

        // Tentukan kode prefix (pemasok diutamakan pada penerimaan)
        if ($pemasok) {
            $kodePrefix = preg_replace('/[^A-Z0-9]/', '', strtoupper($pemasok['kode_pemasok']));
        } elseif ($mitra) {
            $kodePrefix = $mitra['kode_mitra'];
        } else {
            $kodePrefix = 'PMS01';
        }

        // Bobot langsung berat riil KG tanpa pemotongan tara atau pembatasan toleransi
        $netto = isset($data['berat_netto']) && (float)$data['berat_netto'] > 0
            ? round((float)$data['berat_netto'], 2)
            : (isset($data['berat_bruto']) ? round((float)$data['berat_bruto'], 2) : 0.00);
        $bruto = $netto;
        $tara  = 0.000;

        if ($netto <= 0) {
            throw new Exception("Berat barang tidak valid ({$netto} kg). Berat harus lebih besar dari 0.");
        }

        $idLabel = self::generateNextLabelId($kodePrefix, $item['kode_jenis']);
        
        // Surat Jalan Otomatis
        $suratJalan = !empty($data['no_surat_jalan']) ? trim($data['no_surat_jalan']) : (!empty($data['batch_no']) ? trim($data['batch_no']) : self::generateNextSuratJalan($kodePrefix));
        $batchNo = $suratJalan;

        // Ambil harga dari Master Harga jika tidak disertakan
        $hargaPerKg = isset($data['harga_per_kg']) && (float)$data['harga_per_kg'] > 0
            ? (float)$data['harga_per_kg']
            : ($mitra ? MasterHarga::getHargaByMitraAndItem($mitra['id'], $item['id']) : 0.00);
        $totalNilai = round($netto * $hargaPerKg, 2);

        $tglProd = !empty($data['tgl_produksi']) ? $data['tgl_produksi'] : date('Y-m-d');
        $lokasiId = !empty($data['lokasi_id']) ? (int)$data['lokasi_id'] : null;
        $operatorId = !empty($data['operator_id']) ? (int)$data['operator_id'] : 3;

        $sql = "
            INSERT INTO labels (
                id_label, mitra_id, pemasok_id, item_id, lokasi_id, berat_bruto, berat_tara, berat_netto,
                harga_per_kg, total_nilai, batch_no, no_surat_jalan, tgl_produksi, status, operator_timbang_id, catatan
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'AKTIF', ?, ?)
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute([
            $idLabel,
            $mitraId,
            $pemasokId,
            $item['id'],
            $lokasiId,
            $bruto,
            $tara,
            $netto,
            $hargaPerKg,
            $totalNilai,
            $batchNo,
            $suratJalan,
            $tglProd,
            $operatorId,
            $data['catatan'] ?? null
        ]);

        return self::findByBarcode($idLabel);
    }

    /**
     * Buat sekumpulan label barcode sekaligus (Massal / Batch Generator)
     * Catatan: Sesuai instruksi, saat pembuatan barcode massal berat belum diinput (0.00 kg).
     * Bobot riil akan diinput/discan kemudian pada menu timbang berat.
     */
    public static function createBulk(array $data): array {
        $db = Database::getConnection();

        // Ambil data pemasok (prioritas untuk penerimaan barang masuk)
        $pemasokId = !empty($data['pemasok_id']) ? (int)$data['pemasok_id'] : null;
        $pemasok = $pemasokId ? Pemasok::findById($pemasokId) : null;

        $mitraId = !empty($data['mitra_id']) ? (int)$data['mitra_id'] : null;
        $mitra = $mitraId ? Mitra::findById($mitraId) : null;

        $item = Item::findById((int)$data['item_id']);
        if (!$item) throw new Exception("Item / Produk tidak ditemukan");

        // Tentukan kode prefix label (pemasok diutamakan)
        if ($pemasok) {
            $kodePrefix = preg_replace('/[^A-Z0-9]/', '', strtoupper($pemasok['kode_pemasok']));
        } elseif ($mitra) {
            $kodePrefix = $mitra['kode_mitra'];
        } else {
            $kodePrefix = 'PMS01';
        }

        $qty = max(1, min(200, (int)($data['quantity'] ?? 1)));
        
        // Bobot default 0.00 untuk barcode massal belum ditimbang
        $netto = isset($data['berat_netto']) && (float)$data['berat_netto'] > 0
            ? round((float)$data['berat_netto'], 2) : 0.00;
        $bruto = $netto;
        $tara  = 0.000;

        $suratJalan = !empty($data['no_surat_jalan'])
            ? trim($data['no_surat_jalan'])
            : self::generateNextSuratJalan($kodePrefix);
        $batchNo = $suratJalan;

        $hargaPerKg = $mitra ? MasterHarga::getHargaByMitraAndItem($mitra['id'], $item['id']) : 0.00;
        $totalNilai = round($netto * $hargaPerKg, 2);
        $tglProd = date('Y-m-d');
        $lokasiId = !empty($data['lokasi_id']) ? (int)$data['lokasi_id'] : null;
        $operatorId = 3;

        $results = [];
        $sql = "
            INSERT INTO labels (
                id_label, mitra_id, pemasok_id, item_id, lokasi_id, berat_bruto, berat_tara, berat_netto,
                harga_per_kg, total_nilai, batch_no, no_surat_jalan, tgl_produksi, status, operator_timbang_id, catatan
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'AKTIF', ?, ?)
        ";
        $stmt = $db->prepare($sql);

        for ($i = 0; $i < $qty; $i++) {
            $idLabel = self::generateNextLabelId($kodePrefix, $item['kode_jenis']);
            $stmt->execute([
                $idLabel,
                $mitraId,
                $pemasokId,
                $item['id'],
                $lokasiId,
                $bruto,
                $tara,
                $netto,
                $hargaPerKg,
                $totalNilai,
                $batchNo,
                $suratJalan,
                $tglProd,
                $operatorId,
                "Batch Barcode Penerimaan ({$qty} pcs)"
            ]);

            $lbl = self::findByBarcode($idLabel);
            if ($lbl) {
                $compactCode = self::getCompactBarcode($idLabel);
                $lbl['barcode_svg'] = \App\Services\BarcodeService::generateCode128Svg($compactCode, 120, 2.0);
                $lbl['qr_svg'] = \App\Services\BarcodeService::generateQrSvg($idLabel, 120);
                $results[] = $lbl;
            }
        }

        return $results;
    }

    /**
     * Update bobot riil timbangan untuk barcode yang sudah dicetak (Scan Barcode & Timbang Berat)
     */
    /**
     * Membuat 1 label kemasan baru yang langsung memiliki bobot riil timbangan (Alur Satu Pintu Penerimaan)
     */
    public static function createWeighedBox(array $data): array {
        $db = Database::getConnection();

        $pemasokId = !empty($data['pemasok_id']) ? (int)$data['pemasok_id'] : 0;
        $pemasok = $pemasokId > 0 ? Pemasok::findById($pemasokId) : null;
        if (!$pemasok) throw new Exception("Pemasok wajib dipilih.");

        $itemId = !empty($data['item_id']) ? (int)$data['item_id'] : 0;
        $item = $itemId > 0 ? Item::findById($itemId) : null;
        if (!$item) throw new Exception("Produk / Item wajib dipilih.");

        $netto = isset($data['berat_netto']) ? round((float)$data['berat_netto'], 2) : 0.00;
        if ($netto <= 0) {
            throw new Exception("Bobot timbangan harus lebih besar dari 0 KG.");
        }

        $noSj = trim($data['no_surat_jalan'] ?? '');
        if (empty($noSj)) {
            throw new Exception("Nomor Surat Jalan wajib diisi.");
        }

        $kodePrefix = preg_replace('/[^A-Z0-9]/', '', strtoupper($pemasok['kode_pemasok']));
        $idLabel = self::generateNextLabelId($kodePrefix, $item['kode_jenis']);

        // Ambil harga beli pemasok jika ada master_harga_pemasok
        $hargaBeliPerKg = 0.00;
        try {
            $stmtHrg = $db->prepare("SELECT harga_per_kg FROM master_harga_pemasok WHERE pemasok_id = ? AND item_id = ? LIMIT 1");
            $stmtHrg->execute([$pemasokId, $itemId]);
            $hargaBeliPerKg = (float)($stmtHrg->fetchColumn() ?: 0.00);
        } catch (\Throwable $e) {}
        if ($hargaBeliPerKg <= 0) {
            throw new Exception("Harga beli pemasok untuk produk ini belum diatur di Master Harga Pemasok. Atur harga terlebih dahulu.");
        }

        $totalNilai = round($netto * $hargaBeliPerKg, 2);
        $tglProd = date('Y-m-d');
        $operatorId = !empty($data['operator_id']) ? (int)$data['operator_id'] : (int)($_SESSION['user_login']['id'] ?? 3);

        self::pastikanKolomHargaBeli($db);

        $sql = "
            INSERT INTO labels (
                id_label, pemasok_id, mitra_id, item_id, lokasi_id, berat_bruto, berat_tara, berat_netto,
                harga_per_kg, harga_beli_per_kg, total_nilai, batch_no, no_surat_jalan, tgl_produksi, status, is_timbang_selesai, operator_timbang_id, catatan
            ) VALUES (?, ?, NULL, ?, NULL, ?, 0, ?, ?, ?, ?, ?, ?, ?, 'AKTIF', 1, ?, ?)
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute([
            $idLabel,
            $pemasokId,
            $item['id'],
            $netto,
            $netto,
            $hargaBeliPerKg,
            $hargaBeliPerKg,
            $totalNilai,
            $noSj,
            $noSj,
            $tglProd,
            $operatorId,
            "Penerimaan Timbang Langsung"
        ]);

        $lbl = self::findByBarcode($idLabel);
        if (!$lbl) throw new Exception("Gagal membuat data barcode.");
        $compactCode = self::getCompactBarcode($idLabel);
        $lbl['barcode_svg'] = \App\Services\BarcodeService::generateCode128Svg($compactCode, 120, 2.0);
        $lbl['qr_svg'] = \App\Services\BarcodeService::generateQrSvg($idLabel, 120);

        return $lbl;
    }

    /**
     * Mengambil ringkasan sesi penerimaan aktif berdasarkan nomor Surat Jalan
     */
    public static function getInboundSessionSummary(string $noSj): array {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            SELECT 
                l.id, l.id_label, l.berat_netto, l.harga_beli_per_kg, l.total_nilai, l.created_at, l.updated_at,
                i.id AS item_id, i.nama_item, i.kode_item,
                j.nama_jenis,
                p.nama_pemasok, p.kode_pemasok
            FROM labels l
            JOIN items i ON l.item_id = i.id
            JOIN jenis_barang j ON i.jenis_id = j.id
            LEFT JOIN pemasok p ON l.pemasok_id = p.id
            WHERE l.no_surat_jalan = ?
            ORDER BY l.id DESC
        ");
        $stmt->execute([trim($noSj)]);
        $rows = $stmt->fetchAll();

        $totalBox = count($rows);
        $totalBerat = 0.0;
        $totalNilai = 0.0;
        $productMap = [];

        foreach ($rows as $r) {
            $w = (float)$r['berat_netto'];
            $totalBerat += $w;
            $itId = $r['item_id'];
            if (!isset($productMap[$itId])) {
                $productMap[$itId] = [
                    'item_id' => $itId,
                    'nama_item' => $r['nama_item'],
                    'kode_item' => $r['kode_item'],
                    'nama_jenis' => $r['nama_jenis'],
                    'box_count' => 0,
                    'total_berat' => 0.0,
                    'total_nilai' => 0.0
                ];
            }
            $productMap[$itId]['box_count']++;
            $productMap[$itId]['total_berat'] += $w;
            $productMap[$itId]['total_nilai'] += (float)($r['total_nilai'] ?? 0);
            $totalNilai += (float)($r['total_nilai'] ?? 0);
        }
        foreach ($productMap as &$pm) {
            $pm['harga_rata'] = $pm['total_berat'] > 0 ? round($pm['total_nilai'] / $pm['total_berat'], 2) : 0.0;
            $pm['total_nilai'] = round($pm['total_nilai'], 2);
        }
        unset($pm);

        return [
            'no_surat_jalan' => $noSj,
            'total_box' => $totalBox,
            'total_berat' => round($totalBerat, 2),
            'total_nilai' => round($totalNilai, 2),
            'products' => array_values($productMap),
            'recent_boxes' => array_slice($rows, 0, 15)
        ];
    }

    public static function updateWeight(string $barcode, float $netto, ?int $operatorId = null): array {
        $db = Database::getConnection();
        $label = self::findByBarcode($barcode);
        if (!$label) {
            throw new Exception("Barcode '{$barcode}' tidak ditemukan di sistem.");
        }

        if ($netto <= 0) {
            throw new Exception("Berat timbangan harus lebih besar dari 0 KG.");
        }

        // Pencegahan Duplikasi Data: Barcode yang sudah ditimbang tidak dapat ditimbang kembali
        if ((float)($label['berat_netto'] ?? 0) > 0) {
            $formattedWeight = number_format((float)$label['berat_netto'], 2, ',', '.');
            throw new Exception("Barcode '{$barcode}' sudah digunakan dan sudah ditimbang sebelumnya ({$formattedWeight} KG). Tidak dapat discan atau ditimbang kembali untuk menghindari duplikasi data.");
        }

        if ($label['status'] !== 'AKTIF') {
            throw new Exception("Barcode '{$barcode}' berstatus '{$label['status']}' dan tidak dapat ditimbang.");
        }

        $hargaPerKg = MasterHarga::getHargaByMitraAndItem((int)$label['mitra_id'], (int)$label['item_id']);
        $totalNilai = round($netto * $hargaPerKg, 2);

        $stmt = $db->prepare("
            UPDATE labels 
            SET berat_bruto = ?, berat_tara = 0, berat_netto = ?, harga_per_kg = ?, total_nilai = ?,
                operator_timbang_id = COALESCE(?, operator_timbang_id), updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $stmt->execute([
            $netto,
            $netto,
            $hargaPerKg,
            $totalNilai,
            $operatorId,
            $label['id']
        ]);

        $updated = self::findByBarcode($barcode);
        $compactCode = self::getCompactBarcode($updated['id_label']);
        $updated['barcode_svg'] = \App\Services\BarcodeService::generateCode128Svg($compactCode, 120, 2.0);
        $updated['qr_svg'] = \App\Services\BarcodeService::generateQrSvg($updated['id_label'], 120);
        return $updated;
    }

    /**
     * Mengambil daftar label barcode terbaru untuk widget dashboard, mutasi, dan pengeluaran
     */
    public static function getRecent(int $limit = 10): array {
        $db = Database::getConnection();
        $sql = "
            SELECT 
                l.*,
                p.kode_pemasok, p.nama_pemasok,
                m.kode_mitra, m.nama_mitra, m.warna_identitas,
                i.kode_item, i.nama_item,
                j.kode_jenis, j.nama_jenis,
                COALESCE(m.nama_mitra, 'Gudang Utama') AS nama_lokasi
            FROM labels l
            LEFT JOIN pemasok p ON l.pemasok_id = p.id
            LEFT JOIN mitra m ON l.mitra_id = m.id
            JOIN items i ON l.item_id = i.id
            JOIN jenis_barang j ON i.jenis_id = j.id
            ORDER BY l.id DESC
            LIMIT ?
        ";
        $stmt = $db->prepare($sql);
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Ambil seluruh data penerimaan / label dengan filter lengkap dan pagination
     */
    public static function getFiltered(array $filters = [], int $limit = 25, int $offset = 0): array {
        $db = Database::getConnection();
        $where = ["1=1"];
        $params = [];

        if (!empty($filters['status'])) {
            if ($filters['status'] === 'AKTIF') {
                $where[] = "l.status = 'AKTIF'";
            } elseif ($filters['status'] === 'KELUAR') {
                $where[] = "l.status = 'KELUAR'";
            }
        }

        if (!empty($filters['weighed_status'])) {
            if ($filters['weighed_status'] === 'SUDAH') {
                $where[] = "l.berat_netto > 0";
            } elseif ($filters['weighed_status'] === 'BELUM') {
                $where[] = "l.berat_netto <= 0";
            }
        }

        if (!empty($filters['pemasok_id'])) {
            $where[] = "l.pemasok_id = ?";
            $params[] = (int)$filters['pemasok_id'];
        }

        if (!empty($filters['only_gudang_utama'])) {
            $where[] = "(l.mitra_id IS NULL OR l.mitra_id = 0)";
        } elseif (!empty($filters['mitra_id'])) {
            $where[] = "l.mitra_id = ?";
            $params[] = (int)$filters['mitra_id'];
        }

        if (!empty($filters['lokasi_id'])) {
            $where[] = "l.lokasi_id = ?";
            $params[] = (int)$filters['lokasi_id'];
        }

        if (!empty($filters['no_surat_jalan'])) {
            $where[] = "l.no_surat_jalan = ?";
            $params[] = trim($filters['no_surat_jalan']);
        }

        if (!empty($filters['item_id'])) {
            $where[] = "l.item_id = ?";
            $params[] = (int)$filters['item_id'];
        }

        if (!empty($filters['start_date'])) {
            $where[] = "DATE(l.created_at) >= ?";
            $params[] = trim($filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $where[] = "DATE(l.created_at) <= ?";
            $params[] = trim($filters['end_date']);
        }

        if (!empty($filters['date']) && empty($filters['start_date']) && empty($filters['end_date'])) {
            $where[] = "DATE(l.created_at) = ?";
            $params[] = trim($filters['date']);
        }

        if (!empty($filters['search'])) {
            $s = '%' . trim($filters['search']) . '%';
            $where[] = "(l.id_label LIKE ? OR l.no_surat_jalan LIKE ? OR l.batch_no LIKE ? OR i.nama_item LIKE ? OR p.nama_pemasok LIKE ? OR p.kode_pemasok LIKE ? OR m.nama_mitra LIKE ? OR m.kode_mitra LIKE ?)";
            $params[] = $s;
            $params[] = $s;
            $params[] = $s;
            $params[] = $s;
            $params[] = $s;
            $params[] = $s;
            $params[] = $s;
            $params[] = $s;
        }

        $whereClause = implode(' AND ', $where);
        $sql = "
            SELECT 
                l.*,
                p.kode_pemasok, p.nama_pemasok,
                m.kode_mitra, m.nama_mitra, m.warna_identitas, m.kode_gudang_accurate,
                i.kode_item, i.nama_item,
                j.kode_jenis, j.nama_jenis,
                COALESCE(m.nama_mitra, 'Gudang Utama') AS nama_lokasi,
                u.nama_lengkap AS nama_operator,
                last_trx.waktu_mutasi,
                last_trx.no_bast,
                last_trx.no_transaksi AS no_trx_mutasi,
                last_trx.nama_mitra_tujuan,
                last_trx.kode_mitra_tujuan
            FROM labels l
            LEFT JOIN pemasok p ON l.pemasok_id = p.id
            LEFT JOIN mitra m ON l.mitra_id = m.id
            JOIN items i ON l.item_id = i.id
            JOIN jenis_barang j ON i.jenis_id = j.id
            LEFT JOIN users u ON l.operator_timbang_id = u.id
            LEFT JOIN (
                SELECT 
                    d.label_id,
                    t.created_at AS waktu_mutasi,
                    t.no_bast,
                    t.no_transaksi,
                    mt.nama_mitra AS nama_mitra_tujuan,
                    mt.kode_mitra AS kode_mitra_tujuan,
                    ROW_NUMBER() OVER (PARTITION BY d.label_id ORDER BY t.id DESC) AS rn
                FROM transaksi_stok_detail d
                JOIN transaksi_stok t ON d.transaksi_id = t.id
                LEFT JOIN mitra mt ON t.mitra_tujuan_id = mt.id
                WHERE t.jenis_transaksi = 'MUTASI' OR t.no_bast IS NOT NULL OR t.alur_pergerakan IS NOT NULL
            ) last_trx ON last_trx.label_id = l.id AND last_trx.rn = 1
            WHERE {$whereClause}
            ORDER BY l.id DESC
            LIMIT ? OFFSET ?
        ";

        $stmt = $db->prepare($sql);
        $idx = 1;
        foreach ($params as $p) {
            $stmt->bindValue($idx++, $p);
        }
        $stmt->bindValue($idx++, $limit, PDO::PARAM_INT);
        $stmt->bindValue($idx++, $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Hitung total baris data yang cocok dengan filter untuk kalkulasi halaman pagination
     */
    public static function countFiltered(array $filters = []): int {
        $db = Database::getConnection();
        $where = ["1=1"];
        $params = [];

        if (!empty($filters['status'])) {
            if ($filters['status'] === 'AKTIF') {
                $where[] = "l.status = 'AKTIF'";
            } elseif ($filters['status'] === 'KELUAR') {
                $where[] = "l.status = 'KELUAR'";
            }
        }

        if (!empty($filters['weighed_status'])) {
            if ($filters['weighed_status'] === 'SUDAH') {
                $where[] = "l.berat_netto > 0";
            } elseif ($filters['weighed_status'] === 'BELUM') {
                $where[] = "l.berat_netto <= 0";
            }
        }

        if (!empty($filters['pemasok_id'])) {
            $where[] = "l.pemasok_id = ?";
            $params[] = (int)$filters['pemasok_id'];
        }

        if (!empty($filters['only_gudang_utama'])) {
            $where[] = "(l.mitra_id IS NULL OR l.mitra_id = 0)";
        } elseif (!empty($filters['mitra_id'])) {
            $where[] = "l.mitra_id = ?";
            $params[] = (int)$filters['mitra_id'];
        }

        if (!empty($filters['lokasi_id'])) {
            $where[] = "l.lokasi_id = ?";
            $params[] = (int)$filters['lokasi_id'];
        }

        if (!empty($filters['no_surat_jalan'])) {
            $where[] = "l.no_surat_jalan = ?";
            $params[] = trim($filters['no_surat_jalan']);
        }

        if (!empty($filters['item_id'])) {
            $where[] = "l.item_id = ?";
            $params[] = (int)$filters['item_id'];
        }

        if (!empty($filters['start_date'])) {
            $where[] = "DATE(l.created_at) >= ?";
            $params[] = trim($filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $where[] = "DATE(l.created_at) <= ?";
            $params[] = trim($filters['end_date']);
        }

        if (!empty($filters['date']) && empty($filters['start_date']) && empty($filters['end_date'])) {
            $where[] = "DATE(l.created_at) = ?";
            $params[] = trim($filters['date']);
        }

        if (!empty($filters['search'])) {
            $s = '%' . trim($filters['search']) . '%';
            $where[] = "(l.id_label LIKE ? OR l.no_surat_jalan LIKE ? OR l.batch_no LIKE ? OR i.nama_item LIKE ? OR p.nama_pemasok LIKE ? OR p.kode_pemasok LIKE ? OR m.nama_mitra LIKE ? OR m.kode_mitra LIKE ?)";
            $params[] = $s;
            $params[] = $s;
            $params[] = $s;
            $params[] = $s;
            $params[] = $s;
            $params[] = $s;
            $params[] = $s;
            $params[] = $s;
        }

        $whereClause = implode(' AND ', $where);
        $sql = "
            SELECT COUNT(*) 
            FROM labels l
            LEFT JOIN pemasok p ON l.pemasok_id = p.id
            LEFT JOIN mitra m ON l.mitra_id = m.id
            JOIN items i ON l.item_id = i.id
            WHERE {$whereClause}
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Helper menghasilkan kode barcode kompak (Retail Style ala EAN/Kemasan)
     * Contoh: KH001-PRD01-261108-00001 -> 261108-00001
     * Menghasilkan barcode yang jauh lebih tebal & mudah dibaca scanner laser Taffware
     */
    public static function getCompactBarcode(string $idLabel): string {
        $parts = explode('-', trim($idLabel));
        if (count($parts) >= 4) {
            return $parts[2] . '-' . $parts[3];
        }
        if (count($parts) === 3) {
            return $parts[1] . '-' . $parts[2];
        }
        return $idLabel;
    }

    /**
     * Cari label berdasarkan kode barcode (Mendukung scan Full ID maupun Compact Retail Barcode)
     */
    /**
     * Harga beli pemasok dibekukan per label (kolom harga_beli_per_kg).
     * Kolom harga_per_kg dipakai harga jual saat pengeluaran, jadi tidak boleh dipakai sebagai biaya.
     */
    private static function pastikanKolomHargaBeli(\PDO $db): void {
        static $ok = false;
        if ($ok) return;
        $ada = $db->query("SHOW COLUMNS FROM labels LIKE 'harga_beli_per_kg'")->fetch();
        if (!$ada) {
            $db->exec("ALTER TABLE labels ADD COLUMN harga_beli_per_kg DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER harga_per_kg");
        }
        $ok = true;
    }

    public static function findByBarcode(string $barcode): ?array {
        $db = Database::getConnection();
        $barcode = trim($barcode);
        if (empty($barcode)) return null;

        $sql = "
            SELECT 
                l.*,
                p.kode_pemasok, p.nama_pemasok, p.pic_nama as pic_pemasok, p.telepon as telepon_pemasok,
                m.kode_mitra, m.nama_mitra, m.warna_identitas, m.kode_gudang_accurate,
                i.kode_item, i.nama_item, i.default_tara_kg,
                j.kode_jenis, j.nama_jenis,
                COALESCE(m.nama_mitra, 'Gudang Utama') AS nama_lokasi,
                u.nama_lengkap AS nama_operator
            FROM labels l
            LEFT JOIN pemasok p ON l.pemasok_id = p.id
            LEFT JOIN mitra m ON l.mitra_id = m.id
            JOIN items i ON l.item_id = i.id
            JOIN jenis_barang j ON i.jenis_id = j.id
            LEFT JOIN users u ON l.operator_timbang_id = u.id
            WHERE l.id_label = ? OR l.id_label LIKE ?
            ORDER BY (l.id_label = ?) DESC, l.id DESC
            LIMIT 1
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute([$barcode, '%' . $barcode, $barcode]);
        $res = $stmt->fetch();
        if ($res) {
            $res['last_mutasi'] = self::getLastMutasi((int)$res['id']);
            $res['already_moved'] = !empty($res['last_mutasi']) || !empty($res['mitra_id']);
        }
        return $res ?: null;
    }

    /**
     * Mengambil riwayat mutasi / serah terima BAST terakhir untuk suatu label kemasan
     */
    public static function getLastMutasi(int $labelId): ?array {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            SELECT 
                t.id AS transaksi_id,
                t.no_transaksi,
                t.no_bast,
                t.alur_pergerakan,
                t.created_at AS waktu_mutasi,
                t.nama_penyerah,
                t.nama_penerima,
                t.catatan_serah_terima,
                ma.kode_mitra AS kode_mitra_asal,
                ma.nama_mitra AS nama_mitra_asal,
                mt.kode_mitra AS kode_mitra_tujuan,
                mt.nama_mitra AS nama_mitra_tujuan,
                p.nama_pemasok,
                u.nama_lengkap AS operator_pemroses
            FROM transaksi_stok_detail d
            JOIN transaksi_stok t ON d.transaksi_id = t.id
            LEFT JOIN mitra ma ON t.mitra_asal_id = ma.id
            LEFT JOIN mitra mt ON t.mitra_tujuan_id = mt.id
            LEFT JOIN pemasok p ON t.pemasok_id = p.id
            LEFT JOIN users u ON t.user_id = u.id
            WHERE d.label_id = ? 
              AND (t.jenis_transaksi = 'MUTASI' OR t.no_bast IS NOT NULL OR t.alur_pergerakan IS NOT NULL)
            ORDER BY t.id DESC 
            LIMIT 1
        ");
        $stmt->execute([$labelId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Batalkan (Void) label dengan mencatat alasan
     */
    public static function voidLabel(string $barcode, string $alasan, ?int $userId = null): bool {
        $db = Database::getConnection();
        $label = self::findByBarcode($barcode);
        if (!$label) throw new Exception("Label tidak ditemukan");

        if ($label['status'] === 'KELUAR') {
            throw new Exception("Label sudah keluar, tidak dapat di-void.");
        }

        $stmt = $db->prepare("UPDATE labels SET status = 'VOID', alasan_void = ? WHERE id_label = ?");
        $stmt->execute([$alasan, $barcode]);

        // Catat di audit log
        $audit = $db->prepare("INSERT INTO audit_logs (user_id, action_name, target_table, target_id, reason) VALUES (?, 'VOID_LABEL', 'labels', ?, ?)");
        $audit->execute([$userId, $barcode, $alasan]);

        return true;
    }

    /**
     * Mengambil daftar penerimaan yang dikelompokkan per Surat Jalan / Batch Barcode
     */
    public static function getGroupedPenerimaan(array $filters = [], int $limit = 20, int $offset = 0): array {
        $db = Database::getConnection();
        $where = ["l.no_surat_jalan IS NOT NULL AND TRIM(l.no_surat_jalan) != ''"];
        $params = [];

        if (!empty($filters['no_surat_jalan'])) {
            $where[] = "l.no_surat_jalan = ?";
            $params[] = trim($filters['no_surat_jalan']);
        }

        if (!empty($filters['pemasok_id'])) {
            $where[] = "l.pemasok_id = ?";
            $params[] = (int)$filters['pemasok_id'];
        }

        if (!empty($filters['item_id'])) {
            $where[] = "l.item_id = ?";
            $params[] = (int)$filters['item_id'];
        }

        if (!empty($filters['start_date'])) {
            $where[] = "DATE(l.created_at) >= ?";
            $params[] = trim($filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $where[] = "DATE(l.created_at) <= ?";
            $params[] = trim($filters['end_date']);
        }

        if (!empty($filters['date']) && empty($filters['start_date']) && empty($filters['end_date'])) {
            $where[] = "DATE(l.created_at) = ?";
            $params[] = trim($filters['date']);
        }

        if (!empty($filters['weighed_status'])) {
            if ($filters['weighed_status'] === 'SUDAH') {
                $where[] = "l.berat_netto > 0";
            } elseif ($filters['weighed_status'] === 'BELUM') {
                $where[] = "(l.berat_netto <= 0 OR l.berat_netto IS NULL)";
            }
        }

        if (!empty($filters['search'])) {
            $s = '%' . trim($filters['search']) . '%';
            $where[] = "(l.id_label LIKE ? OR l.no_surat_jalan LIKE ? OR l.batch_no LIKE ? OR i.nama_item LIKE ? OR p.nama_pemasok LIKE ? OR p.kode_pemasok LIKE ?)";
            $params[] = $s;
            $params[] = $s;
            $params[] = $s;
            $params[] = $s;
            $params[] = $s;
            $params[] = $s;
        }

        $whereClause = implode(' AND ', $where);

        // Grouping murni per no_surat_jalan agar nomor surat jalan tidak double di tabel
        $sql = "
            SELECT 
                l.no_surat_jalan,
                SUM(l.status = 'VOID') AS jml_void,
                SUM(l.status = 'KELUAR') AS jml_keluar,
                MAX(CASE WHEN l.status = 'VOID' THEN l.alasan_void END) AS alasan_void,
                MAX(l.pemasok_id) as pemasok_id,
                COALESCE(MAX(p.kode_pemasok), 'PMS-01') AS kode_pemasok,
                COALESCE(MAX(p.nama_pemasok), 'Pemasok Umum') AS nama_pemasok,
                MAX(l.item_id) as item_id,
                CASE 
                    WHEN COUNT(DISTINCT i.nama_item) > 1 THEN GROUP_CONCAT(DISTINCT i.nama_item ORDER BY i.nama_item SEPARATOR ', ')
                    ELSE MAX(i.nama_item)
                END AS nama_item,
                MAX(i.kode_item) as kode_item,
                MAX(j.kode_jenis) as kode_jenis,
                MAX(j.nama_jenis) as nama_jenis,
                COUNT(*) as total_pcs,
                SUM(CASE WHEN l.berat_netto <= 0 OR l.berat_netto IS NULL THEN 1 ELSE 0 END) as belum_ditimbang,
                SUM(CASE WHEN l.berat_netto > 0 THEN 1 ELSE 0 END) as sudah_ditimbang,
                SUM(COALESCE(l.berat_netto, 0)) as total_berat,
                MIN(l.id_label) as min_barcode,
                MAX(l.id_label) as max_barcode,
                MAX(l.created_at) as created_at,
                MAX(l.id) as max_id
            FROM labels l
            LEFT JOIN pemasok p ON l.pemasok_id = p.id
            JOIN items i ON l.item_id = i.id
            JOIN jenis_barang j ON i.jenis_id = j.id
            WHERE {$whereClause}
            GROUP BY l.no_surat_jalan
            ORDER BY MAX(l.id) DESC
            LIMIT ? OFFSET ?
        ";

        $stmt = $db->prepare($sql);
        $idx = 1;
        foreach ($params as $p) {
            $stmt->bindValue($idx++, $p);
        }
        $stmt->bindValue($idx++, $limit, PDO::PARAM_INT);
        $stmt->bindValue($idx++, $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Hitung total kelompok penerimaan untuk pagination (unik per No. Surat Jalan)
     */
    public static function countGroupedPenerimaan(array $filters = []): int {
        $db = Database::getConnection();
        $where = ["l.no_surat_jalan IS NOT NULL AND TRIM(l.no_surat_jalan) != ''"];
        $params = [];

        if (!empty($filters['no_surat_jalan'])) {
            $where[] = "l.no_surat_jalan = ?";
            $params[] = trim($filters['no_surat_jalan']);
        }

        if (!empty($filters['pemasok_id'])) {
            $where[] = "l.pemasok_id = ?";
            $params[] = (int)$filters['pemasok_id'];
        }

        if (!empty($filters['item_id'])) {
            $where[] = "l.item_id = ?";
            $params[] = (int)$filters['item_id'];
        }

        if (!empty($filters['start_date'])) {
            $where[] = "DATE(l.created_at) >= ?";
            $params[] = trim($filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $where[] = "DATE(l.created_at) <= ?";
            $params[] = trim($filters['end_date']);
        }

        if (!empty($filters['date']) && empty($filters['start_date']) && empty($filters['end_date'])) {
            $where[] = "DATE(l.created_at) = ?";
            $params[] = trim($filters['date']);
        }

        if (!empty($filters['weighed_status'])) {
            if ($filters['weighed_status'] === 'SUDAH') {
                $where[] = "l.berat_netto > 0";
            } elseif ($filters['weighed_status'] === 'BELUM') {
                $where[] = "(l.berat_netto <= 0 OR l.berat_netto IS NULL)";
            }
        }

        if (!empty($filters['search'])) {
            $s = '%' . trim($filters['search']) . '%';
            $where[] = "(l.id_label LIKE ? OR l.no_surat_jalan LIKE ? OR l.batch_no LIKE ? OR i.nama_item LIKE ? OR p.nama_pemasok LIKE ? OR p.kode_pemasok LIKE ?)";
            $params[] = $s;
            $params[] = $s;
            $params[] = $s;
            $params[] = $s;
            $params[] = $s;
            $params[] = $s;
        }

        $whereClause = implode(' AND ', $where);

        $sql = "
            SELECT COUNT(DISTINCT l.no_surat_jalan)
            FROM labels l
            LEFT JOIN pemasok p ON l.pemasok_id = p.id
            JOIN items i ON l.item_id = i.id
            WHERE {$whereClause}
        ";

        $stmt = $db->prepare($sql);
        $idx = 1;
        foreach ($params as $p) {
            $stmt->bindValue($idx++, $p);
        }
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    /**
     * Mengambil label barcode dalam 1 Surat Jalan lengkap dengan SVG Barcode
     */
    public static function getBySuratJalan(string $noSj, string $status = 'auto_unweighed'): array {
        $db = Database::getConnection();
        
        $whereClause = "WHERE l.no_surat_jalan = ?";
        if ($status === 'unweighed') {
            $whereClause .= " AND (l.berat_netto IS NULL OR l.berat_netto <= 0)";
        } elseif ($status === 'auto_unweighed') {
            $stmtCheck = $db->prepare("SELECT COUNT(*) FROM labels WHERE no_surat_jalan = ? AND (berat_netto IS NULL OR berat_netto <= 0)");
            $stmtCheck->execute([trim($noSj)]);
            $unweighedCount = (int)$stmtCheck->fetchColumn();

            if ($unweighedCount > 0) {
                $whereClause .= " AND (l.berat_netto IS NULL OR l.berat_netto <= 0)";
            }
        }

        $sql = "
            SELECT 
                l.*,
                p.kode_pemasok, p.nama_pemasok,
                m.kode_mitra, m.nama_mitra, m.warna_identitas,
                i.kode_item, i.nama_item,
                j.kode_jenis, j.nama_jenis,
                COALESCE(m.nama_mitra, 'Gudang Utama') AS nama_lokasi
            FROM labels l
            LEFT JOIN pemasok p ON l.pemasok_id = p.id
            LEFT JOIN mitra m ON l.mitra_id = m.id
            JOIN items i ON l.item_id = i.id
            JOIN jenis_barang j ON i.jenis_id = j.id
            {$whereClause}
            ORDER BY l.id ASC
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute([trim($noSj)]);
        $rows = $stmt->fetchAll();

        foreach ($rows as &$lbl) {
            $compactCode = self::getCompactBarcode($lbl['id_label']);
            $lbl['barcode_svg'] = \App\Services\BarcodeService::generateCode128Svg($compactCode, 120, 2.0);
            $lbl['qr_svg'] = \App\Services\BarcodeService::generateQrSvg($lbl['id_label'], 120);
        }

        return $rows;
    }

    /**
     * Mengambil 1 barcode dan menduplikasinya sebanyak $qty pcs
     */
    public static function getSingleDuplicate(string $barcode, int $qty = 1): array {
        $label = self::findByBarcode($barcode);
        if (!$label) {
            throw new Exception("Barcode '{$barcode}' tidak ditemukan");
        }

        $compactCode = self::getCompactBarcode($label['id_label']);
        $barcodeSvg = \App\Services\BarcodeService::generateCode128Svg($compactCode, 120, 2.0);
        $qrSvg = \App\Services\BarcodeService::generateQrSvg($label['id_label'], 120);

        $label['barcode_svg'] = $barcodeSvg;
        $label['qr_svg'] = $qrSvg;

        $results = [];
        $qty = max(1, min(100, $qty));
        for ($i = 0; $i < $qty; $i++) {
            $results[] = $label;
        }

        return $results;
    }

    /**
     * Mengambil 1 barcode dan menduplikasinya menjadi 10 pcs (1 lembar A4 penuh)
     */
    public static function getSingleDuplicate10(string $barcode): array {
        return self::getSingleDuplicate($barcode, 10);
    }

    /**
     * Mengambil daftar barcode berdasarkan array ID barcode lengkap dengan SVG
     */
    public static function getByBarcodes(array $barcodes): array {
        if (empty($barcodes)) return [];
        $db = Database::getConnection();
        
        $whereParts = [];
        $params = [];
        foreach ($barcodes as $b) {
            $b = trim($b);
            if (empty($b)) continue;
            $whereParts[] = "(l.id_label = ? OR l.id_label LIKE ?)";
            $params[] = $b;
            $params[] = '%' . $b;
        }
        if (empty($whereParts)) return [];
        $whereSql = implode(' OR ', $whereParts);
        
        $sql = "
            SELECT 
                l.*,
                p.kode_pemasok, p.nama_pemasok,
                m.kode_mitra, m.nama_mitra, m.warna_identitas,
                i.kode_item, i.nama_item,
                j.kode_jenis, j.nama_jenis,
                COALESCE(m.nama_mitra, 'Gudang Utama') AS nama_lokasi
            FROM labels l
            LEFT JOIN pemasok p ON l.pemasok_id = p.id
            LEFT JOIN mitra m ON l.mitra_id = m.id
            JOIN items i ON l.item_id = i.id
            JOIN jenis_barang j ON i.jenis_id = j.id
            WHERE {$whereSql}
            ORDER BY l.id ASC
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        foreach ($rows as &$lbl) {
            $compactCode = self::getCompactBarcode($lbl['id_label']);
            $lbl['barcode_svg'] = \App\Services\BarcodeService::generateCode128Svg($compactCode, 120, 2.0);
            $lbl['qr_svg'] = \App\Services\BarcodeService::generateQrSvg($lbl['id_label'], 120);
        }

        return $rows;
    }

    /**
     * Mengambil daftar nomor surat jalan unik yang barangnya masih berstatus AKTIF (Fisik di Gudang)
     */
    public static function getActiveSuratJalanList(?int $mitraId = null, bool $onlyGudangUtama = false): array {
        $db = Database::getConnection();
        $where = ["status = 'AKTIF'", "no_surat_jalan IS NOT NULL", "no_surat_jalan != ''"];
        $params = [];

        if ($onlyGudangUtama) {
            $where[] = "(mitra_id IS NULL OR mitra_id = 0)";
        } elseif ($mitraId) {
            $where[] = "mitra_id = ?";
            $params[] = $mitraId;
        }

        $whereClause = implode(' AND ', $where);
        $stmt = $db->prepare("SELECT DISTINCT no_surat_jalan FROM labels WHERE {$whereClause} ORDER BY no_surat_jalan DESC");
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * Mengambil Log Alur Pergerakan Barang (Pemasok -> Mitra -> Pengeluaran)
     * Menampilkan riwayat perjalanan setiap kemasan/barcode secara runut
     */
    public static function getLifecycleLogs(array $filters = [], int $limit = 50, int $offset = 0): array {
        $db = Database::getConnection();
        $where = ["1=1"];
        $params = [];

        if (!empty($filters['barcode'])) {
            $where[] = "l.id_label LIKE ?";
            $params[] = '%' . trim($filters['barcode']) . '%';
        }

        if (!empty($filters['pemasok_id'])) {
            $where[] = "l.pemasok_id = ?";
            $params[] = (int)$filters['pemasok_id'];
        }

        if (!empty($filters['mitra_id'])) {
            $where[] = "l.mitra_id = ?";
            $params[] = (int)$filters['mitra_id'];
        }

        if (!empty($filters['status'])) {
            $where[] = "l.status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['search'])) {
            $s = '%' . trim($filters['search']) . '%';
            $where[] = "(l.id_label LIKE ? OR l.no_surat_jalan LIKE ? OR i.nama_item LIKE ? OR p.nama_pemasok LIKE ? OR m.nama_mitra LIKE ?)";
            $params[] = $s;
            $params[] = $s;
            $params[] = $s;
            $params[] = $s;
            $params[] = $s;
        }

        $whereClause = implode(' AND ', $where);

        $sql = "
            SELECT 
                l.id,
                l.id_label,
                l.berat_netto,
                l.status,
                l.no_surat_jalan AS sj_pemasok,
                l.tgl_produksi,
                l.created_at AS waktu_terima,
                p.id AS pemasok_id,
                p.kode_pemasok,
                p.nama_pemasok,
                m.id AS mitra_id,
                m.kode_mitra,
                m.nama_mitra,
                m.warna_identitas,
                i.kode_item,
                i.nama_item,
                j.kode_jenis,
                j.nama_jenis,
                last_trx.no_bast,
                last_trx.no_transaksi,
                last_trx.alur_pergerakan,
                last_trx.nama_penyerah,
                last_trx.nama_penerima,
                last_trx.waktu_mutasi,
                last_trx.mitra_asal_nama,
                last_trx.mitra_tujuan_nama,
                last_trx.no_referensi_eksternal
            FROM labels l
            LEFT JOIN pemasok p ON l.pemasok_id = p.id
            LEFT JOIN mitra m ON l.mitra_id = m.id
            JOIN items i ON l.item_id = i.id
            JOIN jenis_barang j ON i.jenis_id = j.id
            LEFT JOIN (
                SELECT 
                    td.label_id,
                    t.no_bast,
                    t.no_transaksi,
                    t.alur_pergerakan,
                    t.nama_penyerah,
                    t.nama_penerima,
                    t.created_at AS waktu_mutasi,
                    t.no_referensi_eksternal,
                    ma.nama_mitra AS mitra_asal_nama,
                    mt.nama_mitra AS mitra_tujuan_nama
                FROM transaksi_stok_detail td
                JOIN transaksi_stok t ON td.transaksi_id = t.id
                LEFT JOIN mitra ma ON t.mitra_asal_id = ma.id
                LEFT JOIN mitra mt ON t.mitra_tujuan_id = mt.id
                WHERE td.id IN (
                    SELECT MAX(td2.id) 
                    FROM transaksi_stok_detail td2 
                    GROUP BY td2.label_id
                )
            ) last_trx ON last_trx.label_id = l.id
            WHERE {$whereClause}
            ORDER BY l.id DESC
            LIMIT ? OFFSET ?
        ";

        $stmt = $db->prepare($sql);
        $idx = 1;
        foreach ($params as $p) {
            $stmt->bindValue($idx++, $p);
        }
        $stmt->bindValue($idx++, $limit, \PDO::PARAM_INT);
        $stmt->bindValue($idx++, $offset, \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        foreach ($rows as &$r) {
            if ($r['status'] === 'KELUAR') {
                $r['tahap_alur'] = 3;
                $r['tahap_label'] = '3. Keluar dari Gudang';
                $r['tahap_badge'] = 'badge-secondary';
            } elseif (!empty($r['mitra_id'])) {
                $r['tahap_alur'] = 2;
                $r['tahap_label'] = '2. Tersimpan di Mitra (' . ($r['kode_mitra'] ?? 'Mitra') . ')';
                $r['tahap_badge'] = 'badge-purple';
            } else {
                $r['tahap_alur'] = 1;
                $r['tahap_label'] = '1. Diterima dari Pemasok';
                $r['tahap_badge'] = 'badge-warning';
            }
        }

        return $rows;
    }

    /**
     * Hitung total log lifecycle untuk pagination
     */
    public static function countLifecycleLogs(array $filters = []): int {
        $db = Database::getConnection();
        $where = ["1=1"];
        $params = [];

        if (!empty($filters['pemasok_id'])) {
            $where[] = "l.pemasok_id = ?";
            $params[] = (int)$filters['pemasok_id'];
        }

        if (!empty($filters['mitra_id'])) {
            $where[] = "l.mitra_id = ?";
            $params[] = (int)$filters['mitra_id'];
        }

        if (!empty($filters['status'])) {
            $where[] = "l.status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['search'])) {
            $s = '%' . trim($filters['search']) . '%';
            $where[] = "(l.id_label LIKE ? OR l.no_surat_jalan LIKE ? OR i.nama_item LIKE ? OR p.nama_pemasok LIKE ? OR m.nama_mitra LIKE ?)";
            $params[] = $s;
            $params[] = $s;
            $params[] = $s;
            $params[] = $s;
            $params[] = $s;
        }

        $whereClause = implode(' AND ', $where);

        $sql = "
            SELECT COUNT(*) 
            FROM labels l
            LEFT JOIN pemasok p ON l.pemasok_id = p.id
            LEFT JOIN mitra m ON l.mitra_id = m.id
            JOIN items i ON l.item_id = i.id
            WHERE {$whereClause}
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Mengambil riwayat mutasi lengkap khusus 1 barcode tertentu untuk popup timeline
     */
    public static function getBarcodeTimeline(string $barcode): array {
        $db = Database::getConnection();
        $label = self::findByBarcode($barcode);
        if (!$label) return [];

        $stmt = $db->prepare("
            SELECT 
                t.*,
                td.berat_netto AS berat_saat_transaksi,
                ma.kode_mitra AS kode_mitra_asal, ma.nama_mitra AS nama_mitra_asal,
                mt.kode_mitra AS kode_mitra_tujuan, mt.nama_mitra AS nama_mitra_tujuan,
                p.nama_pemasok,
                u.nama_lengkap AS operator_nama
            FROM transaksi_stok_detail td
            JOIN transaksi_stok t ON td.transaksi_id = t.id
            LEFT JOIN mitra ma ON t.mitra_asal_id = ma.id
            LEFT JOIN mitra mt ON t.mitra_tujuan_id = mt.id
            LEFT JOIN pemasok p ON t.pemasok_id = p.id
            LEFT JOIN users u ON t.user_id = u.id
            WHERE td.label_id = ?
            ORDER BY t.id ASC
        ");
        $stmt->execute([$label['id']]);
        $transactions = $stmt->fetchAll();

        return [
            'label' => $label,
            'history' => $transactions
        ];
    }

    /**
     * Mengambil daftar Surat Jalan yang masih memiliki barcode belum ditimbang untuk dropdown stasiun timbang.
     * Surat Jalan yang sudah selesai ditimbang tidak dimunculkan lagi di list timbang (hanya muncul di riwayat/history).
     */
    public static function getSuratJalanListForWeighing(): array {
        $db = Database::getConnection();
        $sql = "
            SELECT 
                l.no_surat_jalan,
                l.pemasok_id,
                COALESCE(p.kode_pemasok, 'PMS-01') AS kode_pemasok,
                COALESCE(p.nama_pemasok, 'Pemasok Umum') AS nama_pemasok,
                l.item_id,
                i.nama_item,
                i.kode_item,
                COUNT(*) as total_pcs,
                SUM(CASE WHEN l.berat_netto <= 0 OR l.berat_netto IS NULL THEN 1 ELSE 0 END) as belum_ditimbang,
                SUM(CASE WHEN l.berat_netto > 0 THEN 1 ELSE 0 END) as sudah_ditimbang,
                SUM(COALESCE(l.berat_netto, 0)) as total_berat,
                MAX(l.is_timbang_selesai) as is_timbang_selesai,
                MAX(l.created_at) as created_at,
                MAX(l.id) as max_id
            FROM labels l
            LEFT JOIN pemasok p ON l.pemasok_id = p.id
            JOIN items i ON l.item_id = i.id
            WHERE l.no_surat_jalan IS NOT NULL AND TRIM(l.no_surat_jalan) != ''
            GROUP BY l.no_surat_jalan, l.pemasok_id, p.kode_pemasok, p.nama_pemasok, l.item_id, i.nama_item, i.kode_item
            HAVING belum_ditimbang > 0 AND is_timbang_selesai = 0
            ORDER BY MAX(l.id) DESC
        ";
        $stmt = $db->query($sql);
        return $stmt->fetchAll();
    }

    /**
     * Mengambil detail lengkap Surat Jalan Penerimaan beserta seluruh barcode keranjang (unweighed & weighed)
     * Digunakan untuk interaksi stasiun timbang dan cetak dokumen resmi Surat Jalan Penerimaan Barang Masuk.
     */
    public static function getSuratJalanDetailWithLabels(string $noSj): ?array {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            SELECT 
                l.*,
                COALESCE(p.kode_pemasok, 'PMS-01') AS kode_pemasok,
                COALESCE(p.nama_pemasok, 'Pemasok Umum') AS nama_pemasok,
                COALESCE(p.pic_nama, '-') AS pic_pemasok,
                COALESCE(p.alamat, '-') AS alamat_pemasok,
                COALESCE(p.telepon, '-') AS telepon_pemasok,
                i.nama_item,
                i.kode_item,
                j.kode_jenis,
                j.nama_jenis,
                COALESCE(u.nama_lengkap, 'Operator Penerimaan') AS operator_nama
            FROM labels l
            LEFT JOIN pemasok p ON l.pemasok_id = p.id
            JOIN items i ON l.item_id = i.id
            JOIN jenis_barang j ON i.jenis_id = j.id
            LEFT JOIN users u ON l.operator_timbang_id = u.id
            WHERE l.no_surat_jalan = ?
            ORDER BY l.id ASC
        ");
        $stmt->execute([trim($noSj)]);
        $labels = $stmt->fetchAll();

        if (empty($labels)) {
            return null;
        }

        $unweighed = [];
        $weighed = [];
        $totalBerat = 0.0;

        foreach ($labels as $idx => $lbl) {
            $netto = (float)($lbl['berat_netto'] ?? 0);
            $lblItem = [
                'index' => $idx + 1,
                'id' => $lbl['id'],
                'id_label' => $lbl['id_label'],
                'berat_netto' => $netto,
                'berat_bruto' => (float)($lbl['berat_bruto'] ?? 0),
                'berat_tara' => (float)($lbl['berat_tara'] ?? 0),
                'is_timbang_selesai' => (int)($lbl['is_timbang_selesai'] ?? 0),
                'status' => $lbl['status'],
                'created_at' => $lbl['created_at'],
                'updated_at' => $lbl['updated_at'],
                'operator_nama' => $lbl['operator_nama']
            ];

            if ($netto <= 0) {
                $unweighed[] = $lblItem;
            } else {
                $totalBerat += $netto;
                $weighed[] = $lblItem;
            }
        }

        $first = $labels[0];
        $totalPcs = count($labels);
        $avgBerat = $totalPcs > 0 && count($weighed) > 0 ? round($totalBerat / count($weighed), 2) : 0.0;

        return [
            'no_surat_jalan' => $first['no_surat_jalan'],
            'pemasok_id' => $first['pemasok_id'],
            'kode_pemasok' => $first['kode_pemasok'],
            'nama_pemasok' => $first['nama_pemasok'],
            'pic_pemasok' => $first['pic_pemasok'],
            'alamat_pemasok' => $first['alamat_pemasok'],
            'telepon_pemasok' => $first['telepon_pemasok'],
            'item_id' => $first['item_id'],
            'kode_item' => $first['kode_item'],
            'nama_item' => $first['nama_item'],
            'kode_jenis' => $first['kode_jenis'],
            'nama_jenis' => $first['nama_jenis'],
            'created_at' => $first['created_at'],
            'total_pcs' => $totalPcs,
            'belum_ditimbang' => count($unweighed),
            'sudah_ditimbang' => count($weighed),
            'total_berat' => round($totalBerat, 2),
            'avg_berat' => $avgBerat,
            'is_timbang_selesai' => (int)($first['is_timbang_selesai'] ?? 0),
            'status_timbang' => count($unweighed) === 0 ? 'LENGKAP' : (count($weighed) > 0 ? 'PARSIAL' : 'BELUM_DITIMBANG'),
            'unweighed_labels' => $unweighed,
            'weighed_labels' => $weighed,
            'all_labels' => $labels
        ];
    }

    /**
     * Menandai proses penimbangan untuk nomor Surat Jalan telah selesai oleh operator.
     */
    public static function markSuratJalanTimbangSelesai(string $noSj): bool {
        $db = Database::getConnection();
        $stmt = $db->prepare("UPDATE labels SET is_timbang_selesai = 1 WHERE no_surat_jalan = ?");
        return $stmt->execute([trim($noSj)]);
    }

    /**
     * Cek apakah ada stok sejenis (pemasok & item sama) yang lebih lama mengendap di gudang (FIFO).
     */
    public static function checkFifoOlderStock(int $pemasokId, int $itemId, string $currentCreatedAt, string $currentLabelId): ?array {
        if ($pemasokId <= 0 || $itemId <= 0 || empty($currentCreatedAt)) {
            return null;
        }

        $db = Database::getConnection();
        // Cek kemasan aktif sejenis yang masuk lebih awal (selisih minimal 1 jam untuk mengabaikan cetak 1 batch)
        $stmt = $db->prepare("
            SELECT id_label, created_at, berat_netto, no_surat_jalan
            FROM labels
            WHERE pemasok_id = ?
              AND item_id = ?
              AND status = 'AKTIF'
              AND id_label != ?
              AND created_at < ?
              AND TIMESTAMPDIFF(HOUR, created_at, ?) >= 1
            ORDER BY created_at ASC
        ");
        $stmt->execute([$pemasokId, $itemId, $currentLabelId, $currentCreatedAt, $currentCreatedAt]);
        $older = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (empty($older)) {
            return null;
        }

        $oldest = $older[0];
        $timeDiff = strtotime($currentCreatedAt) - strtotime($oldest['created_at']);
        $daysDiff = max(0, (int)floor($timeDiff / 86400));
        $hoursDiff = max(1, (int)floor($timeDiff / 3600));

        $agingText = $daysDiff >= 1 ? "{$daysDiff} hari lalu" : "{$hoursDiff} jam lalu";

        return [
            'has_older_stock' => true,
            'older_count' => count($older),
            'oldest_barcode' => $oldest['id_label'],
            'oldest_created_at' => $oldest['created_at'],
            'oldest_date' => date('d M Y H:i', strtotime($oldest['created_at'])),
            'days_diff' => $daysDiff,
            'hours_diff' => $hoursDiff,
            'aging_text' => $agingText
        ];
    }

    /**
     * Ambil daftar kemasan aktif terlama mengendap (FIFO) untuk pemasok tertentu
     */
    public static function getFifoActiveList(int $pemasokId, ?int $itemId = null, int $limit = 15): array {
        if ($pemasokId <= 0) return [];

        $db = Database::getConnection();
        $sql = "
            SELECT l.id_label, l.berat_netto, l.created_at, l.no_surat_jalan,
                   i.id AS item_id, i.nama_item, i.kode_item, j.nama_jenis
            FROM labels l
            JOIN master_item i ON l.item_id = i.id
            JOIN master_jenis j ON i.jenis_id = j.id
            WHERE l.pemasok_id = ?
              AND l.status = 'AKTIF'
        ";
        $params = [$pemasokId];

        if ($itemId && $itemId > 0) {
            $sql .= " AND l.item_id = ?";
            $params[] = $itemId;
        }

        $sql .= " ORDER BY l.created_at ASC LIMIT " . (int)$limit;

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $now = time();
        foreach ($rows as &$r) {
            $diff = $now - strtotime($r['created_at']);
            $days = (int)floor($diff / 86400);
            $hours = (int)floor($diff / 3600);
            if ($days >= 1) {
                $r['aging_badge'] = 'danger';
                $r['aging_text'] = "🔴 {$days} Hari di Gudang";
            } elseif ($hours >= 4) {
                $r['aging_badge'] = 'warning';
                $r['aging_text'] = "🟡 {$hours} Jam di Gudang";
            } else {
                $r['aging_badge'] = 'success';
                $r['aging_text'] = '🟢 Hari Ini (Baru)';
            }
        }
        unset($r);

        return $rows;
    }

    /**
     * Verifikasi Fisik Barcode Stok Opname dan Simpan Langsung ke Database
     */
    public static function opnameVerify(string $barcode, ?int $userId = null): array {
        $db = Database::getConnection();
        $barcode = trim($barcode);
        $stmt = $db->prepare("
            SELECT l.*, i.nama_item, p.nama_pemasok, m.nama_mitra
            FROM labels l
            JOIN items i ON l.item_id = i.id
            LEFT JOIN pemasok p ON l.pemasok_id = p.id
            LEFT JOIN mitra m ON l.mitra_id = m.id
            WHERE l.id_label = ? OR l.id_label LIKE ?
            ORDER BY (l.id_label = ?) DESC, l.id DESC
            LIMIT 1
        ");
        $stmt->execute([$barcode, '%' . $barcode, $barcode]);
        $label = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$label) {
            return [
                'success' => false,
                'message' => "Barcode '{$barcode}' tidak terdaftar di sistem inventory!"
            ];
        }

        // Cek apakah barang sudah keluar
        if ($label['status'] === 'KELUAR') {
            return [
                'success' => false,
                'message' => "Barcode '{$label['id_label']}' ({$label['nama_item']}) sudah berstatus KELUAR dari gudang!"
            ];
        }

        // Cek apakah barang sudah dimutasi ke mitra
        if (!empty($label['mitra_id']) && (int)$label['mitra_id'] > 0) {
            $namaMitra = $label['nama_mitra'] ?? 'Mitra Terdaftar';
            return [
                'success' => false,
                'message' => "Barcode '{$label['id_label']}' ({$label['nama_item']}) SUDAH DIMUTASI KE MITRA: {$namaMitra}, sehingga tidak boleh ada di fisik gudang utama!"
            ];
        }

        $now = date('Y-m-d H:i:s');
        $alreadyVerified = !empty($label['last_opname_at']);

        // Update database: simpan waktu verifikasi fisik opname
        $upd = $db->prepare("UPDATE labels SET last_opname_at = ?, opname_user_id = ? WHERE id = ?");
        $upd->execute([$now, $userId, $label['id']]);

        $formattedTime = date('d/m/Y H:i', strtotime($now));

        return [
            'success'          => true,
            'message'          => $alreadyVerified 
                ? "Barcode '{$label['id_label']}' sudah pernah terverifikasi di database (diperbarui: {$formattedTime})."
                : "Kemasan '{$label['id_label']}' COCOK & BERHASIL DIVERIFIKASI FISIK KE DATABASE!",
            'already_verified' => $alreadyVerified,
            'verified_at'      => $now,
            'formatted_time'   => $formattedTime,
            'id_label'         => $label['id_label'],
            'barcode'          => $label['id_label'],
            'scanned_barcode'  => $barcode,
            'item_name'        => $label['nama_item']
        ];
    }
}


