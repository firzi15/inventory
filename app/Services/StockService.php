<?php
/**
 * Service Kalkulasi & Agregasi Stok Multi-Mitra
 */

namespace App\Services;

use App\Core\Database;

class StockService {
    /**
     * Matriks Stok Real-Time: 7 Mitra x Kategori Jenis Barang
     */
    public static function getMatrixStock(): array {
        $db = Database::getConnection();

        // Ambil semua mitra aktif (termasuk pemetaan gudang Accurate)
        $mitraList = $db->query("SELECT id, kode_mitra, nama_mitra, kode_gudang_accurate, warna_identitas FROM mitra WHERE is_active = 1 ORDER BY kode_mitra ASC")->fetchAll();
        // Ambil semua jenis barang aktif
        $jenisList = $db->query("SELECT id, kode_jenis, nama_jenis FROM jenis_barang WHERE is_active = 1 ORDER BY kode_jenis ASC")->fetchAll();

        // Agregasi stok aktif
        $sql = "
            SELECT 
                l.mitra_id,
                j.id AS jenis_id,
                COUNT(l.id) AS total_label,
                COALESCE(SUM(l.berat_netto), 0) AS total_kg
            FROM labels l
            JOIN items i ON l.item_id = i.id
            JOIN jenis_barang j ON i.jenis_id = j.id
            WHERE l.status = 'AKTIF'
            GROUP BY l.mitra_id, j.id
        ";
        $stockRows = $db->query($sql)->fetchAll();

        // Susun pivot tabel mitra x jenis
        $matrix = [];
        $totalsPerJenis = [];
        $totalsPerMitra = [];

        foreach ($stockRows as $row) {
            $mId = $row['mitra_id'];
            $jId = $row['jenis_id'];
            $matrix[$mId][$jId] = [
                'kg'    => (float)$row['total_kg'],
                'label' => (int)$row['total_label']
            ];

            if (!isset($totalsPerJenis[$jId])) {
                $totalsPerJenis[$jId] = ['kg' => 0.0, 'label' => 0];
            }
            $totalsPerJenis[$jId]['kg'] += (float)$row['total_kg'];
            $totalsPerJenis[$jId]['label'] += (int)$row['total_label'];

            if (!isset($totalsPerMitra[$mId])) {
                $totalsPerMitra[$mId] = ['kg' => 0.0, 'label' => 0];
            }
            $totalsPerMitra[$mId]['kg'] += (float)$row['total_kg'];
            $totalsPerMitra[$mId]['label'] += (int)$row['total_label'];
        }

        return [
            'mitraList'      => $mitraList,
            'jenisList'      => $jenisList,
            'matrix'         => $matrix,
            'totalsPerJenis' => $totalsPerJenis,
            'totalsPerMitra' => $totalsPerMitra
        ];
    }

    /**
     * Ambil rincian stok per item/SKU untuk mitra tertentu (atau semua) dengan Master Harga & Valuasi
     */
    public static function getStockPerItem($filters = null): array {
        $db = Database::getConnection();
        $params = [];
        $where = "WHERE l.status = 'AKTIF'";

        $mitraId = null;
        $jenisId = null;
        $itemId = null;
        $search = null;
        $sort = null;

        if (is_array($filters)) {
            $mitraId = isset($filters['mitra_id']) && $filters['mitra_id'] !== '' ? (int)$filters['mitra_id'] : null;
            $jenisId = isset($filters['jenis_id']) && $filters['jenis_id'] !== '' ? (int)$filters['jenis_id'] : null;
            $itemId = isset($filters['item_id']) && $filters['item_id'] !== '' ? (int)$filters['item_id'] : null;
            $search = !empty($filters['search']) ? trim($filters['search']) : null;
            $sort = !empty($filters['sort']) ? trim($filters['sort']) : null;
        } elseif (is_numeric($filters)) {
            $mitraId = (int)$filters;
        }

        if ($mitraId !== null && $mitraId > 0) {
            $where .= " AND l.mitra_id = ?";
            $params[] = $mitraId;
        }

        if ($jenisId !== null && $jenisId > 0) {
            $where .= " AND j.id = ?";
            $params[] = $jenisId;
        }

        if ($itemId !== null && $itemId > 0) {
            $where .= " AND i.id = ?";
            $params[] = $itemId;
        }

        if ($search !== null && $search !== '') {
            $where .= " AND (i.nama_item LIKE ? OR i.kode_item LIKE ? OR m.nama_mitra LIKE ? OR m.kode_mitra LIKE ?)";
            $s = "%{$search}%";
            $params[] = $s;
            $params[] = $s;
            $params[] = $s;
            $params[] = $s;
        }

        $orderBy = "m.kode_mitra ASC, total_kg DESC";
        if ($sort === 'kg_asc') {
            $orderBy = "total_kg ASC";
        } elseif ($sort === 'kg_desc') {
            $orderBy = "total_kg DESC";
        } elseif ($sort === 'kemasan_desc') {
            $orderBy = "total_label DESC";
        } elseif ($sort === 'nama_asc') {
            $orderBy = "i.nama_item ASC";
        } elseif ($sort === 'nilai_desc') {
            $orderBy = "total_nilai DESC";
        }

        $sql = "
            SELECT 
                i.id AS item_id,
                i.kode_item,
                i.nama_item,
                j.id AS jenis_id,
                j.nama_jenis,
                m.id AS mitra_id,
                m.kode_mitra,
                m.nama_mitra,
                m.warna_identitas,
                m.kode_gudang_accurate,
                COUNT(l.id) AS total_label,
                COALESCE(SUM(l.berat_netto), 0) AS total_kg,
                AVG(l.berat_netto) AS rata_rata_kg,
                COALESCE(SUM(l.berat_netto * l.harga_beli_per_kg), 0) / NULLIF(SUM(l.berat_netto), 0) AS harga_per_kg,
                COALESCE(SUM(l.berat_netto * l.harga_beli_per_kg), 0) AS total_nilai,
                COALESCE(mh.harga_per_kg, 0) AS harga_jual_per_kg,
                (COALESCE(SUM(l.berat_netto), 0) * COALESCE(mh.harga_per_kg, 0)) AS total_nilai_jual
            FROM labels l
            JOIN items i ON l.item_id = i.id
            JOIN jenis_barang j ON i.jenis_id = j.id
            JOIN mitra m ON l.mitra_id = m.id
            LEFT JOIN master_harga mh ON mh.mitra_id = l.mitra_id AND mh.item_id = l.item_id
            {$where}
            GROUP BY i.id, i.kode_item, i.nama_item, j.id, j.nama_jenis, m.id, m.kode_mitra, m.nama_mitra, m.warna_identitas, m.kode_gudang_accurate, mh.harga_per_kg
            ORDER BY {$orderBy}
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Nilai persediaan dihitung dari harga beli yang dibekukan pada tiap label (bukan harga master saat ini),
     * lalu dicocokkan dengan saldo akun 1200 Inventory di jurnal.
     * Selisih berarti ada label atau faktur yang tidak sejalan; daftar Surat Jalan yang berbeda ikut dikembalikan.
     */
    public static function rekonsiliasiPersediaan(): array {
        $db = Database::getConnection();
        JurnalService::ensureTables();

        $fisik = (float)$db->query("SELECT COALESCE(SUM(berat_netto * harga_beli_per_kg), 0) FROM labels WHERE status = 'AKTIF'")->fetchColumn();
        $fisikMitra = (float)$db->query("SELECT COALESCE(SUM(berat_netto * harga_beli_per_kg), 0) FROM labels WHERE status = 'AKTIF' AND mitra_id IS NOT NULL")->fetchColumn();

        // Mutasi akun 1200 per kelompok dokumen (jurnal pembalik ikut dihitung ke dokumen asalnya)
        $mut = ['masuk' => 0.0, 'keluar' => 0.0, 'lain' => 0.0];
        $q = $db->query("
            SELECT REPLACE(j.sumber_tipe, '_BALIK', '') AS tipe, COALESCE(SUM(jb.debit - jb.kredit), 0) AS net
            FROM jurnal_baris jb JOIN jurnal j ON j.id = jb.jurnal_id
            WHERE jb.kode_akun = '1200' AND " . \App\Services\JurnalService::AKTIF . "
            GROUP BY REPLACE(j.sumber_tipe, '_BALIK', '')
        ");
        foreach ($q->fetchAll(\PDO::FETCH_ASSOC) as $r) {
            if ($r['tipe'] === 'PURCHASE_INV') $mut['masuk'] += (float)$r['net'];
            elseif ($r['tipe'] === 'PENGELUARAN') $mut['keluar'] += (float)$r['net'];
            else $mut['lain'] += (float)$r['net'];
        }
        $coa = $mut['masuk'] + $mut['keluar'] + $mut['lain'];

        $sj = $db->query("
            SELECT l.no_surat_jalan,
                   COALESCE(SUM(l.berat_netto * l.harga_beli_per_kg), 0) AS nilai_label,
                   (SELECT COALESCE(SUM(pi.total_nominal), 0) FROM purchase_invoices pi
                     WHERE pi.no_surat_jalan = l.no_surat_jalan AND pi.tipe_invoice = 'STOK' AND pi.status_bayar <> 'BATAL') AS nilai_faktur
            FROM labels l
            WHERE l.status <> 'VOID' AND l.no_surat_jalan IS NOT NULL AND l.no_surat_jalan <> ''
            GROUP BY l.no_surat_jalan
            HAVING ABS(nilai_label - nilai_faktur) > 0.5
            ORDER BY l.no_surat_jalan
        ")->fetchAll(\PDO::FETCH_ASSOC);

        return [
            'fisik'    => $fisik,
            'fisik_mitra'  => $fisikMitra,
            'fisik_gudang' => $fisik - $fisikMitra,
            'coa'      => $coa,
            'selisih'  => round($coa - $fisik, 2),
            'masuk'    => $mut['masuk'],
            'keluar'   => $mut['keluar'],
            'lain'     => $mut['lain'],
            'sj_beda'  => $sj,
            'cocok'    => abs($coa - $fisik) < 0.5,
        ];
    }

    /**
     * Ringkasan Stok Fisik & Valuasi Finansial per 7 Mitra Bisnis
     */
    public static function getStockSummaryPerMitra(): array {
        $db = Database::getConnection();
        $sql = "
            SELECT 
                m.id,
                m.kode_mitra,
                m.nama_mitra,
                m.kode_gudang_accurate,
                m.warna_identitas,
                COUNT(DISTINCT l.item_id) AS total_sku,
                COUNT(l.id) AS total_kemasan,
                COALESCE(SUM(l.berat_netto), 0) AS total_kg,
                COALESCE(SUM(l.berat_netto * l.harga_beli_per_kg), 0) AS total_nilai_stok,
                COALESCE(SUM(l.berat_netto * COALESCE(mh.harga_per_kg, 0)), 0) AS total_nilai_jual
            FROM mitra m
            LEFT JOIN labels l ON m.id = l.mitra_id AND l.status = 'AKTIF'
            LEFT JOIN master_harga mh ON mh.mitra_id = l.mitra_id AND mh.item_id = l.item_id
            WHERE m.is_active = 1
            GROUP BY m.id, m.kode_mitra, m.nama_mitra, m.kode_gudang_accurate, m.warna_identitas
            ORDER BY m.kode_mitra ASC
        ";
        return $db->query($sql)->fetchAll();
    }

    /**
     * KPI Dashboard Ringkasan
     */
    public static function getDashboardKpi(): array {
        $db = Database::getConnection();

        // 1. Total Stok Aktif (Kg & Label)
        $stock = $db->query("SELECT COUNT(*) AS total_label, COALESCE(SUM(berat_netto), 0) AS total_kg FROM labels WHERE status = 'AKTIF'")->fetch();

        // 2. Transaksi Hari Ini
        $today = date('Y-m-d');
        $stmtTrx = $db->prepare("SELECT COUNT(*) AS total_trx, COALESCE(SUM(total_kg), 0) AS total_kg FROM transaksi_stok WHERE jenis_transaksi <> 'PENGELUARAN_BATAL' AND DATE(created_at) = ?");
        $stmtTrx->execute([$today]);
        $trxToday = $stmtTrx->fetch();

        // 3. Antrean Pending Accurate
        $pendingAcc = $db->query("SELECT COUNT(*) AS cnt FROM accurate_sync_queue WHERE status = 'PENDING'")->fetch();

        // 4. Mitra Aktif
        $mitraCount = $db->query("SELECT COUNT(*) AS cnt FROM mitra WHERE is_active = 1")->fetch();

        return [
            'total_stok_kg'    => (float)($stock['total_kg'] ?? 0),
            'total_label_aktif'=> (int)($stock['total_label'] ?? 0),
            'trx_hari_ini'     => (int)($trxToday['total_trx'] ?? 0),
            'trx_kg_hari_ini'  => (float)($trxToday['total_kg'] ?? 0),
            'queue_pending'    => (int)($pendingAcc['cnt'] ?? 0),
            'total_mitra'      => (int)($mitraCount['cnt'] ?? 7)
        ];
    }

    /**
     * Ringkasan Dashboard: angka harian, daftar tindakan, dan tren masuk/keluar 14 hari.
     */
    public static function getDashboardOverview(int $days = 14): array {
        $db = Database::getConnection();
        $today = date('Y-m-d');

        // Stok gudang utama (belum dimutasi ke mitra)
        $gudang = self::getStockGudangUtamaSummary();

        // Penerimaan hari ini (label berdasarkan surat jalan)
        $stmt = $db->prepare("
            SELECT COUNT(*) AS pcs, COALESCE(SUM(berat_netto), 0) AS kg, COUNT(DISTINCT no_surat_jalan) AS sj
            FROM labels
            WHERE status <> 'VOID' AND no_surat_jalan IS NOT NULL AND TRIM(no_surat_jalan) != '' AND DATE(created_at) = ?
        ");
        $stmt->execute([$today]);
        $masuk = $stmt->fetch() ?: [];

        // Pengeluaran hari ini
        $stmt = $db->prepare("
            SELECT COUNT(*) AS nota, COALESCE(SUM(total_kg), 0) AS kg
            FROM transaksi_stok
            WHERE jenis_transaksi = 'PENGELUARAN' AND DATE(created_at) = ?
        ");
        $stmt->execute([$today]);
        $keluar = $stmt->fetch() ?: [];

        // Tren masuk vs keluar
        $start = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));
        $stmt = $db->prepare("
            SELECT DATE(created_at) AS d, COALESCE(SUM(berat_netto), 0) AS kg
            FROM labels
            WHERE status <> 'VOID' AND no_surat_jalan IS NOT NULL AND TRIM(no_surat_jalan) != '' AND DATE(created_at) >= ?
            GROUP BY DATE(created_at)
        ");
        $stmt->execute([$start]);
        $masukPerHari = [];
        foreach ($stmt->fetchAll() as $r) { $masukPerHari[$r['d']] = (float)$r['kg']; }

        $stmt = $db->prepare("
            SELECT DATE(created_at) AS d, COALESCE(SUM(total_kg), 0) AS kg
            FROM transaksi_stok
            WHERE jenis_transaksi = 'PENGELUARAN' AND DATE(created_at) >= ?
            GROUP BY DATE(created_at)
        ");
        $stmt->execute([$start]);
        $keluarPerHari = [];
        foreach ($stmt->fetchAll() as $r) { $keluarPerHari[$r['d']] = (float)$r['kg']; }

        $trend = ['labels' => [], 'masuk' => [], 'keluar' => []];
        for ($i = $days - 1; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-{$i} days"));
            $trend['labels'][] = date('d/m', strtotime($d));
            $trend['masuk'][]  = round($masukPerHari[$d] ?? 0, 2);
            $trend['keluar'][] = round($keluarPerHari[$d] ?? 0, 2);
        }

        // Tindakan yang perlu dikerjakan
        $sjBelumTimbang = count(\App\Models\Label::getSuratJalanListForWeighing());
        $piutang = \App\Models\Piutang::getSummaryKpi();
        $utang = \App\Models\Utang::getSummaryKpi();

        return [
            'gudang_kg'          => (float)$gudang['total_kg'],
            'gudang_keranjang'   => (int)$gudang['total_barcode'],
            'gudang_mengendap'   => (int)$gudang['count_mengendap'],
            'masuk_kg'           => (float)($masuk['kg'] ?? 0),
            'masuk_pcs'          => (int)($masuk['pcs'] ?? 0),
            'masuk_sj'           => (int)($masuk['sj'] ?? 0),
            'keluar_kg'          => (float)($keluar['kg'] ?? 0),
            'keluar_nota'        => (int)($keluar['nota'] ?? 0),
            'sj_belum_timbang'   => $sjBelumTimbang,
            'piutang_overdue'    => (float)$piutang['total_overdue'],
            'utang_overdue'      => (float)$utang['total_overdue'],
            'trend'              => $trend,
        ];
    }

    /**
     * Data Tren & Analitik Stok Lengkap (Mitra, Kategori, & Pergerakan 7 Hari)
     */
    public static function getStockTrendData(): array {
        $db = Database::getConnection();

        // 1. Distribusi per Mitra (Volume & Kemasan)
        $mitraTrends = $db->query("
            SELECT 
                m.id, m.kode_mitra, m.nama_mitra, m.kode_gudang_accurate, m.warna_identitas,
                COUNT(l.id) AS total_kemasan,
                COALESCE(SUM(l.berat_netto), 0) AS total_kg
            FROM mitra m
            LEFT JOIN labels l ON m.id = l.mitra_id AND l.status = 'AKTIF'
            WHERE m.is_active = 1
            GROUP BY m.id, m.kode_mitra, m.nama_mitra, m.kode_gudang_accurate, m.warna_identitas
            ORDER BY total_kg DESC
        ")->fetchAll();

        // 2. Distribusi per Kategori Barang
        $kategoriTrends = $db->query("
            SELECT 
                j.id, j.kode_jenis, j.nama_jenis,
                COUNT(l.id) AS total_kemasan,
                COALESCE(SUM(l.berat_netto), 0) AS total_kg
            FROM jenis_barang j
            LEFT JOIN items i ON j.id = i.jenis_id
            LEFT JOIN labels l ON i.id = l.item_id AND l.status = 'AKTIF'
            WHERE j.is_active = 1
            GROUP BY j.id, j.kode_jenis, j.nama_jenis
            ORDER BY total_kg DESC
        ")->fetchAll();

        // Total akumulasi
        $grandTotalKg = 0;
        $grandTotalKemasan = 0;
        foreach ($mitraTrends as $m) {
            $grandTotalKg += (float)$m['total_kg'];
            $grandTotalKemasan += (int)$m['total_kemasan'];
        }

        // Kalkulasi persentase per mitra
        foreach ($mitraTrends as &$m) {
            $m['pct_kg'] = $grandTotalKg > 0 ? round(((float)$m['total_kg'] / $grandTotalKg) * 100, 1) : 0;
        }
        unset($m);

        // Kalkulasi persentase per kategori
        foreach ($kategoriTrends as &$k) {
            $k['pct_kg'] = $grandTotalKg > 0 ? round(((float)$k['total_kg'] / $grandTotalKg) * 100, 1) : 0;
        }
        unset($k);

        // 3. Tren Alur Pergerakan 7 Hari Terakhir
        $trendDays = [];
        for ($i = 6; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-$i days"));
            $trendDays[$d] = [
                'tanggal'    => $d,
                'label'      => date('d M', strtotime($d)),
                'in_kg'      => 0.0,
                'in_pcs'     => 0,
                'out_kg'     => 0.0,
                'out_pcs'    => 0,
                'mutasi_kg'  => 0.0,
                'mutasi_pcs' => 0
            ];
        }

        // Inbound / Penerimaan (labels created)
        $inbound = $db->query("
            SELECT DATE(created_at) as tgl, COUNT(*) as pcs, COALESCE(SUM(berat_netto), 0) as total_kg
            FROM labels
            WHERE status <> 'VOID' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            GROUP BY DATE(created_at)
        ")->fetchAll();

        foreach ($inbound as $in) {
            if (isset($trendDays[$in['tgl']])) {
                $trendDays[$in['tgl']]['in_kg'] = (float)$in['total_kg'];
                $trendDays[$in['tgl']]['in_pcs'] = (int)$in['pcs'];
            }
        }

        // Outbound & Mutasi dari transaksi_stok
        $txRows = $db->query("
            SELECT DATE(created_at) as tgl, jenis_transaksi, COUNT(*) as cnt, COALESCE(SUM(total_kg), 0) as total_kg, COALESCE(SUM(total_label), 0) as total_label
            FROM transaksi_stok
            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            GROUP BY DATE(created_at), jenis_transaksi
        ")->fetchAll();

        $totalOutKg7d = 0;
        $totalOutPcs7d = 0;
        $totalMutasiKg7d = 0;
        $totalMutasiPcs7d = 0;

        foreach ($txRows as $tx) {
            if (isset($trendDays[$tx['tgl']])) {
                if ($tx['jenis_transaksi'] === 'PENGELUARAN') {
                    $trendDays[$tx['tgl']]['out_kg'] += (float)$tx['total_kg'];
                    $trendDays[$tx['tgl']]['out_pcs'] += (int)$tx['total_label'];
                    $totalOutKg7d += (float)$tx['total_kg'];
                    $totalOutPcs7d += (int)$tx['total_label'];
                } elseif ($tx['jenis_transaksi'] === 'MUTASI') {
                    $trendDays[$tx['tgl']]['mutasi_kg'] += (float)$tx['total_kg'];
                    $trendDays[$tx['tgl']]['mutasi_pcs'] += (int)$tx['total_label'];
                    $totalMutasiKg7d += (float)$tx['total_kg'];
                    $totalMutasiPcs7d += (int)$tx['total_label'];
                }
            }
        }

        $topMitra = !empty($mitraTrends) ? $mitraTrends[0] : null;
        $topKategori = !empty($kategoriTrends) ? $kategoriTrends[0] : null;
        $avgKgPerPcs = $grandTotalKemasan > 0 ? round($grandTotalKg / $grandTotalKemasan, 2) : 0;

        return [
            'grand_total_kg'      => $grandTotalKg,
            'grand_total_kemasan' => $grandTotalKemasan,
            'avg_kg_per_kemasan'  => $avgKgPerPcs,
            'mitra_trends'        => $mitraTrends,
            'kategori_trends'     => $kategoriTrends,
            'daily_movements'     => array_values($trendDays),
            'total_out_kg_7d'     => $totalOutKg7d,
            'total_out_pcs_7d'    => $totalOutPcs7d,
            'total_mutasi_kg_7d'  => $totalMutasiKg7d,
            'total_mutasi_pcs_7d' => $totalMutasiPcs7d,
            'top_mitra'           => $topMitra,
            'top_kategori'        => $topKategori
        ];
    }

    /**
     * Helper privat: Bangun klausa WHERE dan parameter untuk stok gudang utama
     */
    private static function buildStockGudangUtamaWhere(array $filters): array {
        $params = [];
        $where = "WHERE l.status = 'AKTIF' AND (l.mitra_id IS NULL OR l.mitra_id = 0)";

        if (!empty($filters['pemasok_id'])) {
            $where .= " AND l.pemasok_id = ?";
            $params[] = (int)$filters['pemasok_id'];
        }
        if (!empty($filters['jenis_id'])) {
            $where .= " AND i.jenis_id = ?";
            $params[] = (int)$filters['jenis_id'];
        }
        if (!empty($filters['item_id'])) {
            $where .= " AND l.item_id = ?";
            $params[] = (int)$filters['item_id'];
        }
        if (!empty($filters['status_timbang'])) {
            if ($filters['status_timbang'] === 'SUDAH') {
                $where .= " AND l.berat_netto > 0";
            } elseif ($filters['status_timbang'] === 'BELUM') {
                $where .= " AND (l.berat_netto <= 0 OR l.berat_netto IS NULL)";
            }
        }
        if (!empty($filters['date_range'])) {
            if ($filters['date_range'] === 'today') {
                $where .= " AND DATE(l.created_at) = CURDATE()";
            } elseif ($filters['date_range'] === 'yesterday') {
                $where .= " AND DATE(l.created_at) = (CURDATE() - INTERVAL 1 DAY)";
            } elseif ($filters['date_range'] === 'last_7_days') {
                $where .= " AND l.created_at >= (CURDATE() - INTERVAL 7 DAY)";
            } elseif ($filters['date_range'] === 'older_3_days') {
                $where .= " AND l.created_at < (NOW() - INTERVAL 3 DAY)";
            }
        }

        $startDate = !empty($filters['start_date']) ? trim($filters['start_date']) : (!empty($filters['filter_start_date']) ? trim($filters['filter_start_date']) : '');
        $endDate   = !empty($filters['end_date']) ? trim($filters['end_date']) : (!empty($filters['filter_end_date']) ? trim($filters['filter_end_date']) : '');

        if (!empty($startDate)) {
            $where .= " AND DATE(l.created_at) >= ?";
            $params[] = $startDate;
        }
        if (!empty($endDate)) {
            $where .= " AND DATE(l.created_at) <= ?";
            $params[] = $endDate;
        }
        if (!empty($filters['search'])) {
            $where .= " AND (l.id_label LIKE ? OR l.no_surat_jalan LIKE ? OR i.nama_item LIKE ? OR i.kode_item LIKE ? OR p.nama_pemasok LIKE ? OR p.kode_pemasok LIKE ?)";
            $s = '%' . trim($filters['search']) . '%';
            $params[] = $s;
            $params[] = $s;
            $params[] = $s;
            $params[] = $s;
            $params[] = $s;
            $params[] = $s;
        }

        return [$where, $params];
    }

    /**
     * Ringkasan KPI Sisa Stok Gudang Utama (Belum dialokasikan / dimutasi ke Mitra)
     */
    public static function getStockGudangUtamaSummary(array $filters = []): array {
        $db = Database::getConnection();
        list($where, $params) = self::buildStockGudangUtamaWhere($filters);

        $sql = "
            SELECT 
                COUNT(l.id) AS total_barcode,
                COALESCE(SUM(l.berat_netto), 0) AS total_kg,
                COALESCE(SUM(l.berat_netto * l.harga_beli_per_kg), 0) AS total_nilai_hpp,
                COUNT(DISTINCT l.pemasok_id) AS total_pemasok,
                COUNT(DISTINCT l.item_id) AS total_item,
                SUM(CASE WHEN l.berat_netto > 0 THEN 1 ELSE 0 END) AS sudah_ditimbang,
                SUM(CASE WHEN l.berat_netto <= 0 OR l.berat_netto IS NULL THEN 1 ELSE 0 END) AS belum_ditimbang,
                SUM(CASE WHEN l.berat_netto > 0 THEN l.berat_netto ELSE 0 END) AS total_kg_siap,
                SUM(CASE WHEN DATE(l.created_at) = CURDATE() THEN 1 ELSE 0 END) AS count_hari_ini,
                SUM(CASE WHEN l.created_at < (NOW() - INTERVAL 3 DAY) THEN 1 ELSE 0 END) AS count_mengendap
            FROM labels l
            JOIN items i ON l.item_id = i.id
            LEFT JOIN pemasok p ON l.pemasok_id = p.id
            {$where}
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch() ?: [];

        $totalBarcode = (int)($row['total_barcode'] ?? 0);
        $totalKg = (float)($row['total_kg'] ?? 0);
        $avgKg = $totalBarcode > 0 ? round($totalKg / $totalBarcode, 2) : 0.00;

        return [
            'total_barcode'        => $totalBarcode,
            'total_kg'             => $totalKg,
            'total_nilai_hpp'      => (float)($row['total_nilai_hpp'] ?? 0),
            'total_pemasok'        => (int)($row['total_pemasok'] ?? 0),
            'total_item'           => (int)($row['total_item'] ?? 0),
            'sudah_ditimbang'      => (int)($row['sudah_ditimbang'] ?? 0),
            'belum_ditimbang'      => (int)($row['belum_ditimbang'] ?? 0),
            'total_kg_siap'        => (float)($row['total_kg_siap'] ?? 0),
            'avg_kg_per_keranjang' => $avgKg,
            'count_hari_ini'       => (int)($row['count_hari_ini'] ?? 0),
            'count_mengendap'      => (int)($row['count_mengendap'] ?? 0),
        ];
    }

    /**
     * Rekapitulasi Sisa Stok Gudang Utama per Produk & Pemasok (Ringkasan)
     */
    public static function getStockGudangUtamaPerItem(array $filters = []): array {
        $db = Database::getConnection();
        list($where, $params) = self::buildStockGudangUtamaWhere($filters);

        $sortOrder = "total_kg DESC, total_keranjang DESC";
        $sort = $filters['sort'] ?? '';
        if ($sort === 'fifo' || $sort === 'tgl_asc') {
            $sortOrder = "earliest_masuk ASC, total_kg DESC";
        } elseif ($sort === 'lifo' || $sort === 'tgl_desc') {
            $sortOrder = "latest_masuk DESC, total_kg DESC";
        } elseif ($sort === 'keranjang_desc') {
            $sortOrder = "total_keranjang DESC, total_kg DESC";
        } elseif ($sort === 'keranjang_asc') {
            $sortOrder = "total_keranjang ASC, total_kg ASC";
        } elseif ($sort === 'nama_asc') {
            $sortOrder = "i.nama_item ASC, p.nama_pemasok ASC";
        } elseif ($sort === 'nama_desc') {
            $sortOrder = "i.nama_item DESC, p.nama_pemasok DESC";
        } elseif ($sort === 'pemasok_asc') {
            $sortOrder = "p.nama_pemasok ASC, i.nama_item ASC";
        } elseif ($sort === 'pemasok_desc') {
            $sortOrder = "p.nama_pemasok DESC, i.nama_item DESC";
        } elseif ($sort === 'kategori_asc') {
            $sortOrder = "j.nama_jenis ASC, i.nama_item ASC";
        } elseif ($sort === 'kategori_desc') {
            $sortOrder = "j.nama_jenis DESC, i.nama_item DESC";
        } elseif ($sort === 'kg_asc') {
            $sortOrder = "total_kg ASC, total_keranjang ASC";
        } elseif ($sort === 'kg_desc') {
            $sortOrder = "total_kg DESC, total_keranjang DESC";
        } elseif ($sort === 'sj_asc') {
            $sortOrder = "latest_masuk ASC, latest_surat_jalan ASC";
        } elseif ($sort === 'sj_desc') {
            $sortOrder = "latest_masuk DESC, latest_surat_jalan DESC";
        }

        $sql = "
            SELECT 
                i.id AS item_id,
                i.kode_item,
                i.nama_item,
                j.id AS jenis_id,
                j.kode_jenis,
                j.nama_jenis,
                COALESCE(p.id, 0) AS pemasok_id,
                COALESCE(p.kode_pemasok, '-') AS kode_pemasok,
                COALESCE(p.nama_pemasok, 'Gudang Utama') AS nama_pemasok,
                COUNT(l.id) AS total_keranjang,
                COALESCE(SUM(l.berat_netto), 0) AS total_kg,
                AVG(CASE WHEN l.berat_netto > 0 THEN l.berat_netto ELSE NULL END) AS avg_kg,
                SUM(CASE WHEN l.berat_netto > 0 THEN 1 ELSE 0 END) AS sudah_ditimbang_cnt,
                SUM(CASE WHEN l.berat_netto <= 0 OR l.berat_netto IS NULL THEN 1 ELSE 0 END) AS belum_ditimbang_cnt,
                MAX(l.no_surat_jalan) AS latest_surat_jalan,
                MIN(l.created_at) AS earliest_masuk,
                MAX(l.created_at) AS latest_masuk
            FROM labels l
            JOIN items i ON l.item_id = i.id
            JOIN jenis_barang j ON i.jenis_id = j.id
            LEFT JOIN pemasok p ON l.pemasok_id = p.id
            {$where}
            GROUP BY i.id, i.kode_item, i.nama_item, j.id, j.kode_jenis, j.nama_jenis, p.id, p.kode_pemasok, p.nama_pemasok
            ORDER BY {$sortOrder}
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Rincian Seluruh Barcode Satuan di Sisa Stok Gudang Utama
     */
    public static function getStockGudangUtamaLabels(array $filters = [], int $limit = 20, int $offset = 0): array {
        $db = Database::getConnection();
        list($where, $params) = self::buildStockGudangUtamaWhere($filters);

        $sortOrder = "l.id DESC";
        $sort = $filters['sort'] ?? '';
        if ($sort === 'fifo' || $sort === 'tgl_asc') {
            $sortOrder = "l.created_at ASC, l.id ASC";
        } elseif ($sort === 'lifo' || $sort === 'tgl_desc') {
            $sortOrder = "l.created_at DESC, l.id DESC";
        } elseif ($sort === 'kg_desc') {
            $sortOrder = "l.berat_netto DESC, l.id DESC";
        } elseif ($sort === 'kg_asc') {
            $sortOrder = "l.berat_netto ASC, l.id DESC";
        } elseif ($sort === 'nama_asc') {
            $sortOrder = "i.nama_item ASC, l.id DESC";
        } elseif ($sort === 'nama_desc') {
            $sortOrder = "i.nama_item DESC, l.id DESC";
        } elseif ($sort === 'pemasok_asc') {
            $sortOrder = "p.nama_pemasok ASC, l.id DESC";
        } elseif ($sort === 'pemasok_desc') {
            $sortOrder = "p.nama_pemasok DESC, l.id DESC";
        } elseif ($sort === 'barcode_asc') {
            $sortOrder = "l.id_label ASC";
        } elseif ($sort === 'barcode_desc') {
            $sortOrder = "l.id_label DESC";
        } elseif ($sort === 'sj_asc') {
            $sortOrder = "l.no_surat_jalan ASC, l.id DESC";
        } elseif ($sort === 'sj_desc') {
            $sortOrder = "l.no_surat_jalan DESC, l.id DESC";
        }

        $sql = "
            SELECT 
                l.*,
                i.kode_item,
                i.nama_item,
                j.kode_jenis,
                j.nama_jenis,
                COALESCE(p.kode_pemasok, '-') AS kode_pemasok,
                COALESCE(p.nama_pemasok, 'Gudang Utama') AS nama_pemasok,
                u.nama_lengkap AS operator_nama
            FROM labels l
            JOIN items i ON l.item_id = i.id
            JOIN jenis_barang j ON i.jenis_id = j.id
            LEFT JOIN pemasok p ON l.pemasok_id = p.id
            LEFT JOIN users u ON l.operator_timbang_id = u.id
            {$where}
            ORDER BY {$sortOrder}
            LIMIT {$limit} OFFSET {$offset}
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Hitung Total Barcode untuk Pagination Rincian Stok Gudang Utama
     */
    public static function countStockGudangUtamaLabels(array $filters = []): int {
        $db = Database::getConnection();
        list($where, $params) = self::buildStockGudangUtamaWhere($filters);

        $sql = "
            SELECT COUNT(l.id) AS cnt
            FROM labels l
            JOIN items i ON l.item_id = i.id
            LEFT JOIN pemasok p ON l.pemasok_id = p.id
            {$where}
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return (int)($row['cnt'] ?? 0);
    }

    /**
     * Dapatkan status dan rincian persediaan gudang aktual untuk pemasok tertentu
     * Digunakan saat operator memilih pemasok di Stasiun Pengeluaran Barang
     */
    public static function getStockGudangPerPemasok(int $pemasokId, ?int $mitraTujuanId = null): array {
        $db = Database::getConnection();

        // 1. Ringkasan Total
        $stmtSummary = $db->prepare("
            SELECT 
                COUNT(l.id) AS total_kemasan,
                COALESCE(SUM(l.berat_netto), 0) AS total_kg,
                COUNT(DISTINCT l.item_id) AS total_sku
            FROM labels l
            WHERE l.status = 'AKTIF' AND l.pemasok_id = ?
        ");
        $stmtSummary->execute([$pemasokId]);
        $summary = $stmtSummary->fetch(\PDO::FETCH_ASSOC) ?: [
            'total_kemasan' => 0,
            'total_kg' => 0.0,
            'total_sku' => 0
        ];

        // 2. Rincian stok per SKU & Jenis Barang
        $stmtItems = $db->prepare("
            SELECT 
                i.id AS item_id,
                i.kode_item,
                i.nama_item,
                j.id AS jenis_id,
                j.kode_jenis,
                j.nama_jenis,
                COUNT(l.id) AS total_kemasan,
                COALESCE(SUM(l.berat_netto), 0) AS total_kg,
                COALESCE(mhp.harga_per_kg, 0.00) AS harga_pemasok,
                COALESCE(mhm.harga_per_kg, 0.00) AS harga_mitra
            FROM labels l
            JOIN items i ON l.item_id = i.id
            JOIN jenis_barang j ON i.jenis_id = j.id
            LEFT JOIN master_harga_pemasok mhp ON mhp.pemasok_id = ? AND mhp.item_id = i.id
            LEFT JOIN master_harga mhm ON mhm.mitra_id = ? AND mhm.item_id = i.id
            WHERE l.status = 'AKTIF' AND l.pemasok_id = ?
            GROUP BY i.id, i.kode_item, i.nama_item, j.id, j.kode_jenis, j.nama_jenis, mhp.harga_per_kg, mhm.harga_per_kg
            ORDER BY total_kg DESC
        ");
        $stmtItems->execute([$pemasokId, $mitraTujuanId ?: 0, $pemasokId]);
        $items = $stmtItems->fetchAll(\PDO::FETCH_ASSOC);

        // 3. Barcode aktif siap dikeluarkan (hingga 100 kemasan)
        $stmtLabels = $db->prepare("
            SELECT 
                l.id,
                l.id_label,
                l.berat_netto,
                l.no_surat_jalan,
                l.created_at,
                i.id AS item_id,
                i.nama_item,
                j.id AS jenis_id,
                j.nama_jenis,
                COALESCE(mhp.harga_per_kg, 0.00) AS harga_pemasok,
                COALESCE(mhm.harga_per_kg, 0.00) AS harga_mitra
            FROM labels l
            JOIN items i ON l.item_id = i.id
            JOIN jenis_barang j ON i.jenis_id = j.id
            LEFT JOIN master_harga_pemasok mhp ON mhp.pemasok_id = ? AND mhp.item_id = i.id
            LEFT JOIN master_harga mhm ON mhm.mitra_id = ? AND mhm.item_id = i.id
            WHERE l.status = 'AKTIF' AND l.pemasok_id = ?
            ORDER BY l.id DESC
            LIMIT 100
        ");
        $stmtLabels->execute([$pemasokId, $mitraTujuanId ?: 0, $pemasokId]);
        $labels = $stmtLabels->fetchAll(\PDO::FETCH_ASSOC);

        return [
            'summary' => $summary,
            'items'   => $items,
            'labels'  => $labels
        ];
    }
}


