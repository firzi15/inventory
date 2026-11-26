<?php
// Tombol ekspor Excel + PDF kecil untuk modal rincian.
// Set sebelum include: $emTabel (id tabel, boleh beberapa dipisah koma), $emJudul (ekspresi JS yang menghasilkan judul/nama file).
$__t = htmlspecialchars(json_encode($emTabel ?? ''), ENT_QUOTES);
$__j = htmlspecialchars($emJudul ?? "'Rincian'", ENT_QUOTES);
?>
<span class="no-print" style="display: inline-flex; gap: 6px; align-items: center;">
    <button type="button" class="btn btn-outline btn-sm" onclick="eksporExcel(String(<?= $__j ?>).replace(/[^\w.-]+/g, '_'), <?= $__t ?>)" style="display: inline-flex; align-items: center; gap: 5px; color: var(--ok-fg);" title="Ekspor ke Excel">
        <i data-lucide="file-spreadsheet" style="width: 14px; height: 14px;"></i><span>Excel</span>
    </button>
    <button type="button" class="btn btn-outline btn-sm" onclick="eksporPdf(String(<?= $__j ?>), <?= $__t ?>)" style="display: inline-flex; align-items: center; gap: 5px;" title="Ekspor ke PDF">
        <i data-lucide="file-text" style="width: 14px; height: 14px;"></i><span>PDF</span>
    </button>
</span>
<?php unset($__t, $__j, $emTabel, $emJudul); ?>
