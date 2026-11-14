<?php
// Tombol ekspor laporan (gaya seragam).
// Set sebelum include: $eksporNama (nama file), $eksporJudul (judul PDF, opsional), $eksporTabel (id tabel, opsional).
$eksporNama  = $eksporNama ?? 'Laporan';
$eksporJudul = $eksporJudul ?? str_replace('_', ' ', $eksporNama);
$eksporTabel = $eksporTabel ?? '';
$__n = htmlspecialchars(json_encode($eksporNama), ENT_QUOTES);
$__j = htmlspecialchars(json_encode($eksporJudul), ENT_QUOTES);
$__t = htmlspecialchars(json_encode($eksporTabel), ENT_QUOTES);
?>
<button type="button" class="btn btn-outline" onclick="eksporExcel(<?= $__n ?>, <?= $__t ?>)" style="display: inline-flex; align-items: center; gap: 6px;">
    <i data-lucide="file-spreadsheet" style="width: 16px; height: 16px;"></i>
    <span>Excel</span>
</button>
<button type="button" class="btn btn-outline" onclick="eksporPdf(<?= $__j ?>, <?= $__t ?>)" style="display: inline-flex; align-items: center; gap: 6px;">
    <i data-lucide="file-text" style="width: 16px; height: 16px;"></i>
    <span>PDF</span>
</button>
<?php $eksporTabel = ''; unset($__n, $__j, $__t); ?>
