<?php
/**
 * Tabel umur utang/piutang per dokumen (surat jalan masuk atau nota keluar).
 * Variabel: $agRows [no_dok, tanggal, pihak, tempo, tagihan, bayar, sisa, telat], $agLabelDok, $agLabelPihak, $agLabelTagihan, $agLabelBayar, $agLabelSisa
 */
use App\Core\Helper;
use App\Services\AgingService;

$agWarna = ['aging_belum' => '#0369a1', 'aging_1_7' => '#15803d', 'aging_8_14' => '#b45309', 'aging_15_30' => '#c2410c', 'aging_gt_30' => '#b91c1c'];
?>
<div class="table-responsive">
    <table class="table-custom" data-halaman="25">
        <thead>
            <tr>
                <th style="width: 40px; text-align: center;">No</th>
                <th style="min-width: 150px;"><?= htmlspecialchars($agLabelDok) ?></th>
                <th style="width: 100px;">Tanggal</th>
                <th style="min-width: 170px;"><?= htmlspecialchars($agLabelPihak) ?></th>
                <th style="width: 105px; text-align: center;">Jatuh Tempo</th>
                <th style="text-align: right; min-width: 120px;"><?= htmlspecialchars($agLabelTagihan) ?></th>
                <th style="text-align: right; min-width: 120px;"><?= htmlspecialchars($agLabelBayar) ?></th>
                <th style="text-align: right; min-width: 125px; background: var(--bg-muted);"><?= htmlspecialchars($agLabelSisa) ?></th>
                <?php foreach (AgingService::KOLOM as $k => $lbl): ?>
                    <th style="text-align: right; min-width: 105px; color: <?= $agWarna[$k] ?>;"><?= htmlspecialchars($lbl) ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($agRows)): ?>
                <tr><td colspan="13" style="text-align: center; padding: 32px; color: var(--text-muted);">Tidak ada dokumen yang masih terutang.</td></tr>
            <?php else: $no = 1; foreach ($agRows as $r): $kunci = AgingService::kunci((int)$r['telat']); ?>
                <tr>
                    <td style="text-align: center; color: var(--text-muted);"><?= $no++ ?></td>
                    <td><strong style="font-family: 'JetBrains Mono', monospace; font-size: 12px; color: var(--text-main);"><?= htmlspecialchars($r['no_dok']) ?></strong></td>
                    <td style="font-size: 12px;"><?= date('d/m/Y', strtotime($r['tanggal'])) ?></td>
                    <td><strong style="color: var(--text-main);"><?= htmlspecialchars($r['pihak']) ?></strong></td>
                    <td style="text-align: center; font-size: 12px;">
                        <?= date('d/m/Y', strtotime($r['tempo'])) ?>
                        <?php if ($r['telat'] > 0): ?><div style="font-size: 10.5px; font-weight: 700; color: #b91c1c;">Telat <?= (int)$r['telat'] ?> hr</div><?php endif; ?>
                    </td>
                    <td style="text-align: right; font-family: 'JetBrains Mono', monospace; font-size: 12.5px;"><?= Helper::formatRupiah($r['tagihan']) ?></td>
                    <td style="text-align: right; font-family: 'JetBrains Mono', monospace; font-size: 12.5px; color: var(--ok-fg);"><?= Helper::formatRupiah($r['bayar']) ?></td>
                    <td style="text-align: right; font-family: 'JetBrains Mono', monospace; font-size: 13px; font-weight: 800; background: var(--bg-muted); color: #b45309;"><?= Helper::formatRupiah($r['sisa']) ?></td>
                    <?php foreach (AgingService::KOLOM as $k => $lbl): $ya = ($k === $kunci); ?>
                        <td style="text-align: right; font-family: 'JetBrains Mono', monospace; font-size: 12px; <?= $ya ? 'font-weight: 700; color: ' . $agWarna[$k] . ';' : 'color: #94a3b8;' ?>"><?= $ya ? Helper::formatRupiah($r['sisa']) : '-' ?></td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>
