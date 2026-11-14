<?php
namespace App\Services;

use App\Core\Database;
use Exception;

/**
 * Pengecekan pemakaian data master. Master hanya boleh dihapus kalau tidak dipakai menu lain.
 * Dokumen yang dibatalkan tetap dihitung sebagai pemakaian (jejak riwayat harus utuh).
 */
class MasterGuard
{
    /** Daftar pemakaian: [['menu' => 'Penerimaan (keranjang)', 'jumlah' => 3], ...] */
    public static function pemakaian(string $master, int $id): array
    {
        $db = Database::getConnection();
        $peta = [
            'mitra' => [
                ['Stok gudang dan pengeluaran (keranjang)', "SELECT COUNT(*) FROM labels WHERE mitra_id = ?"],
                ['Pengeluaran (surat jalan keluar)',        "SELECT COUNT(*) FROM transaksi_stok WHERE mitra_id = ? OR mitra_tujuan_id = ? OR mitra_asal_id = ?", 3],
                ['Setoran mitra',                           "SELECT COUNT(*) FROM ar_pembayaran WHERE mitra_id = ?"],
            ],
            'item' => [
                ['Penerimaan dan stok (keranjang)', "SELECT COUNT(*) FROM labels WHERE item_id = ?"],
                ['Rincian faktur pembelian',        "SELECT COUNT(*) FROM purchase_invoice_items WHERE item_id = ?"],
            ],
            'jenis' => [
                ['Produk', "SELECT COUNT(*) FROM items WHERE jenis_id = ?"],
            ],
            'pemasok' => [
                ['Penerimaan (keranjang)',          "SELECT COUNT(*) FROM labels WHERE pemasok_id = ?"],
                ['Transaksi stok',                  "SELECT COUNT(*) FROM transaksi_stok WHERE pemasok_id = ?"],
                ['Payment Out',                "SELECT COUNT(*) FROM purchase_invoices WHERE pemasok_id = ?"],
                ['Pembayaran ke pemasok',           "SELECT COUNT(*) FROM ap_pembayaran WHERE pemasok_id = ?"],
            ],
        ];
        if (!isset($peta[$master])) throw new Exception('Jenis master tidak dikenal.');

        $hasil = [];
        foreach ($peta[$master] as $cek) {
            $st = $db->prepare($cek[1]);
            $st->execute(array_fill(0, $cek[2] ?? 1, $id));
            $n = (int)$st->fetchColumn();
            if ($n > 0) $hasil[] = ['menu' => $cek[0], 'jumlah' => $n];
        }
        return $hasil;
    }

    /** Lempar Exception berisi daftar menu yang masih memakai master ini. */
    public static function pastikanTidakDipakai(string $master, int $id, string $namaMaster): void
    {
        $pakai = self::pemakaian($master, $id);
        if (!$pakai) return;
        $daftar = implode(', ', array_map(fn($p) => "{$p['menu']} ({$p['jumlah']})", $pakai));
        throw new Exception("{$namaMaster} tidak dapat dihapus karena masih dipakai di: {$daftar}. Hapus atau batalkan datanya di menu tersebut terlebih dahulu. Jika sudah tidak dipakai, nonaktifkan saja.");
    }
}
