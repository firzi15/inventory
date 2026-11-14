<?php
// Filter periode seragam: pilihan cepat (hari / minggu / bulan / YTD) + tanggal mulai dan akhir.
// Set sebelum include: $fpDari, $fpSampai ('' = tanpa filter), $fpHidden (parameter tetap, mis. ['page' => 'coa']),
// $fpSemua (true = tampilkan tombol "Semua" untuk menghapus filter).
// Opsional: $fpExtra (HTML filter tambahan di baris kedua) dan $fpLinkExtra (parameter yang ikut di tautan pilihan cepat).
use App\Core\Periode;

$fpDari   = $fpDari ?? '';
$fpSampai = $fpSampai ?? '';
$fpHidden = $fpHidden ?? [];
$fpSemua  = $fpSemua ?? false;
$fpPreset = Periode::daftarPreset();
$fpBase   = 'index.php?' . http_build_query($fpHidden + ($fpLinkExtra ?? []));
$fpExtra  = $fpExtra ?? '';
?>
<form method="GET" action="index.php" class="filter-periode no-print">
    <?php foreach ($fpHidden as $k => $v): ?>
        <input type="hidden" name="<?= htmlspecialchars((string)$k) ?>" value="<?= htmlspecialchars((string)$v) ?>">
    <?php endforeach; ?>
    <div class="fp-preset">
        <?php if ($fpSemua): ?>
            <a href="<?= htmlspecialchars($fpBase) ?>" class="fp-chip <?= $fpDari === '' ? 'aktif' : '' ?>">Semua</a>
        <?php endif; ?>
        <?php foreach ($fpPreset as [$label, $pd, $ps]): ?>
            <a href="<?= htmlspecialchars($fpBase . '&dari=' . $pd . '&sampai=' . $ps) ?>" class="fp-chip <?= ($fpDari === $pd && $fpSampai === $ps) ? 'aktif' : '' ?>"><?= htmlspecialchars($label) ?></a>
        <?php endforeach; ?>
    </div>
    <div class="fp-tanggal">
        <label>Mulai <input type="date" name="dari" value="<?= htmlspecialchars($fpDari) ?>" class="form-control" required></label>
        <label>Sampai <input type="date" name="sampai" value="<?= htmlspecialchars($fpSampai) ?>" class="form-control" required></label>
        <button type="submit" class="btn btn-primary">Terapkan</button>
    </div>
    <?php if ($fpExtra !== ''): ?><div class="fp-extra"><?= $fpExtra ?></div><?php endif; ?>
</form>
