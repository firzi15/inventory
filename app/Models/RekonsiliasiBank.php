<?php
/**
 * Model Rekonsiliasi Bukti Setoran Bank
 * Menyediakan pencocokan sederhana antara nominal pembayaran AR
 * dengan bukti screenshot transfer / foto slip setor tunai dari admin.
 */

namespace App\Models;

use App\Core\Database;
use PDO;
use Exception;

class RekonsiliasiBank {

    /**
     * Ringkasan Status Rekonsiliasi
     */
    public static function getSummaryKpi(array $filters = []): array {
        $pdo = Database::getConnection();

        $where = ["1=1"];
        $params = [];

        if (!empty($filters['mitra_id'])) {
            $where[] = "p.mitra_id = :mitra_id";
            $params[':mitra_id'] = (int)$filters['mitra_id'];
        }
        if (!empty($filters['date_start'])) {
            $where[] = "p.tanggal_bayar >= :date_start";
            $params[':date_start'] = $filters['date_start'];
        }
        if (!empty($filters['date_end'])) {
            $where[] = "p.tanggal_bayar <= :date_end";
            $params[':date_end'] = $filters['date_end'];
        }

        $whereSql = implode(' AND ', $where);

        $sql = "
            SELECT 
                COUNT(p.id) as total_setoran,
                COALESCE(SUM(p.jumlah_bayar), 0) as total_nominal,
                COUNT(CASE WHEN p.status_rekonsiliasi = 'MATCHED' THEN 1 END) as count_matched,
                COALESCE(SUM(CASE WHEN p.status_rekonsiliasi = 'MATCHED' THEN p.jumlah_bayar ELSE 0 END), 0) as nominal_matched,
                COUNT(CASE WHEN p.status_rekonsiliasi != 'MATCHED' OR p.status_rekonsiliasi IS NULL THEN 1 END) as count_pending,
                COALESCE(SUM(CASE WHEN p.status_rekonsiliasi != 'MATCHED' OR p.status_rekonsiliasi IS NULL THEN p.jumlah_bayar ELSE 0 END), 0) as nominal_pending
            FROM ar_pembayaran p
            WHERE {$whereSql}
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'total_setoran'   => (int)$res['total_setoran'],
            'total_nominal'   => (float)$res['total_nominal'],
            'count_matched'   => (int)$res['count_matched'],
            'nominal_matched' => (float)$res['nominal_matched'],
            'count_pending'   => (int)$res['count_pending'],
            'nominal_pending' => (float)$res['nominal_pending']
        ];
    }

    /**
     * Mengambil Seluruh Transaksi Setoran AR untuk Dicocokkan Buktinya
     */
    public static function getRekonsiliasiList(array $filters = []): array {
        $pdo = Database::getConnection();

        $where = ["1=1"];
        $params = [];

        if (!empty($filters['status'])) {
            if ($filters['status'] === 'MATCHED') {
                $where[] = "p.status_rekonsiliasi = 'MATCHED'";
            } elseif ($filters['status'] === 'PENDING') {
                $where[] = "(p.status_rekonsiliasi != 'MATCHED' OR p.status_rekonsiliasi IS NULL)";
            }
        }

        if (!empty($filters['metode_bayar'])) {
            $where[] = "p.metode_bayar = :metode";
            $params[':metode'] = $filters['metode_bayar'];
        }

        if (!empty($filters['mitra_id'])) {
            $where[] = "p.mitra_id = :mitra_id";
            $params[':mitra_id'] = (int)$filters['mitra_id'];
        }

        if (!empty($filters['date_start'])) {
            $where[] = "p.tanggal_bayar >= :date_start";
            $params[':date_start'] = $filters['date_start'];
        }

        if (!empty($filters['date_end'])) {
            $where[] = "p.tanggal_bayar <= :date_end";
            $params[':date_end'] = $filters['date_end'];
        }

        if (!empty($filters['search'])) {
            $where[] = "(p.no_bukti_bayar LIKE :search OR m.nama_mitra LIKE :search OR p.keterangan LIKE :search OR p.catatan_rekonsiliasi LIKE :search)";
            $params[':search'] = '%' . trim($filters['search']) . '%';
        }

        $whereSql = implode(' AND ', $where);

        $sql = "
            SELECT 
                p.id,
                p.no_bukti_bayar,
                p.mitra_id,
                p.tanggal_bayar,
                p.metode_bayar,
                p.bank_tujuan,
                p.jumlah_bayar as nominal_ar,
                p.keterangan as keterangan_ar,
                COALESCE(p.status_rekonsiliasi, 'PENDING') as status_rekonsiliasi,
                p.bukti_transfer as bukti_gambar,
                p.catatan_rekonsiliasi,
                p.verified_at,
                m.kode_mitra,
                m.nama_mitra,
                uKasir.nama_lengkap as nama_kasir,
                uVerif.nama_lengkap as nama_verifikator,
                (
                    SELECT GROUP_CONCAT(
                        COALESCE(ts.no_referensi_eksternal, ts.no_bast, ts.no_transaksi) 
                        SEPARATOR ', '
                    )
                    FROM ar_alokasi_pembayaran al
                    JOIN transaksi_stok ts ON al.transaksi_stok_id = ts.id
                    WHERE al.pembayaran_id = p.id
                ) as daftar_nota_terpotong
            FROM ar_pembayaran p
            JOIN mitra m ON p.mitra_id = m.id
            LEFT JOIN users uKasir ON p.user_id = uKasir.id
            LEFT JOIN users uVerif ON p.verified_by = uVerif.id
            WHERE {$whereSql}
            ORDER BY 
                CASE WHEN p.status_rekonsiliasi = 'MATCHED' THEN 1 ELSE 0 END ASC,
                p.tanggal_bayar DESC, 
                p.id DESC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Lampirkan Bukti Gambar Setoran (Screenshot M-Banking / Slip Struk Setor Tunai CDM)
     * Otomatis mengubah status menjadi MATCHED
     */
    public static function attachBukti(int $arId, string $filePath, ?string $catatan = null, int $userId = 1): bool {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            UPDATE ar_pembayaran SET
                status_rekonsiliasi  = 'MATCHED',
                nominal_aktual       = jumlah_bayar,
                selisih_nominal      = 0.00,
                bukti_transfer       = :file_path,
                catatan_rekonsiliasi = :catatan,
                verified_at          = NOW(),
                verified_by          = :user_id
            WHERE id = :id
        ");

        return $stmt->execute([
            ':file_path' => $filePath,
            ':catatan'   => $catatan ?: null,
            ':user_id'   => $userId,
            ':id'        => $arId
        ]);
    }

    /**
     * Hapus Bukti / Batalkan Status Matched (Kembalikan ke PENDING)
     */
    public static function removeBukti(int $arId): bool {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            UPDATE ar_pembayaran SET
                status_rekonsiliasi  = 'PENDING',
                nominal_aktual       = NULL,
                selisih_nominal      = 0.00,
                bukti_transfer       = NULL,
                catatan_rekonsiliasi = NULL,
                verified_at          = NULL,
                verified_by          = NULL
            WHERE id = ?
        ");

        return $stmt->execute([$arId]);
    }
}
