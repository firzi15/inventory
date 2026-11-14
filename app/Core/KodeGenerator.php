<?php
namespace App\Core;

/**
 * Pembuat kode master otomatis. Nomor berikutnya = nomor terbesar yang sudah dipakai + 1,
 * jadi tidak pernah bentrok dan tidak bergantung pada urutan id.
 */
class KodeGenerator
{
    /**
     * @param string $prefix Awalan kode, mis. 'PMS', 'M', 'PRD', 'IK'
     * @param string $sep    Pemisah antara awalan dan nomor ('-' atau '')
     */
    public static function next(string $table, string $column, string $prefix, int $pad = 2, string $sep = '-'): string
    {
        $db = Database::getConnection();
        $awal = $prefix . $sep;
        $st = $db->prepare("SELECT `$column` FROM `$table` WHERE `$column` LIKE ?");
        $st->execute([addcslashes($awal, '%_') . '%']);
        $max = 0;
        foreach ($st->fetchAll(\PDO::FETCH_COLUMN) as $kode) {
            $sisa = substr((string)$kode, strlen($awal));
            if ($sisa !== '' && ctype_digit($sisa)) {
                $max = max($max, (int)$sisa);
            }
        }
        return $awal . str_pad((string)($max + 1), $pad, '0', STR_PAD_LEFT);
    }
}
