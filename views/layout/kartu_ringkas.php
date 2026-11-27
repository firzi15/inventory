<?php
// Baris kartu ringkasan di atas filter riwayat. Set sebelum include: $krKartu = [[judul, nilai, keterangan, warna, ikon], ...]
?>
<div class="no-print" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px; margin-bottom: 14px;">
    <?php foreach ($krKartu as [$krJudul, $krNilai, $krKet, $krWarna, $krIkon]): ?>
        <div class="card" style="padding: 14px 16px; display: flex; align-items: center; gap: 12px;">
            <div style="width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; background: var(--bg-muted); color: <?= $krWarna ?>; flex-shrink: 0;">
                <i data-lucide="<?= htmlspecialchars($krIkon) ?>" style="width: 20px; height: 20px;"></i>
            </div>
            <div style="min-width: 0;">
                <div style="font-size: 11.5px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.3px;"><?= htmlspecialchars($krJudul) ?></div>
                <div style="font-size: 20px; font-weight: 800; color: <?= $krWarna ?>; font-family: 'JetBrains Mono', monospace; white-space: nowrap;"><?= htmlspecialchars($krNilai) ?></div>
                <div style="font-size: 11.5px; color: var(--text-muted);"><?= htmlspecialchars($krKet) ?></div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<?php unset($krKartu, $krJudul, $krNilai, $krKet, $krWarna, $krIkon); ?>
