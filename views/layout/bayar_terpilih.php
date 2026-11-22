<?php
/**
 * Bayar / setor gelondongan: modal untuk dokumen yang dicentang di Payment Out (faktur pemasok) atau Payment In (nota mitra).
 * Set sebelum include:
 *   $btJenis     'ap' (bayar pemasok) atau 'ar' (setoran mitra)
 *   $btPihakId   id pemasok / mitra yang sedang difilter
 *   $btPihakNama nama pemasok / mitra
 * Baris tabel memakai checkbox class "cek-bayar-{jenis}" dengan data-no, data-sisa, data-tempo, dan value = id dokumen.
 * Nominal boleh kurang dari total; pembagiannya ke dokumen yang jatuh temponya paling lama lebih dahulu (sama dengan server).
 */
$btAp = $btJenis === 'ap';
?>
<style>
    /* Tombol bayar/setor terpilih terlihat jelas nonaktif selama belum ada dokumen yang dicentang */
    #btnBayarTerpilih:disabled { opacity: .45; cursor: not-allowed; filter: grayscale(.35); box-shadow: none; }
    /* Dialog konfirmasi / pesan (SweetAlert, z-index bawaan 1060) harus tampil di atas modal ini (z-index 9999) */
    body .swal2-container { z-index: 10050 !important; }
</style>
<div id="modalBayarTerpilih" class="modal-backdrop" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center; overflow-y: auto; padding: 20px;">
    <div class="card" style="width: 100%; max-width: 760px; margin: auto; padding: 24px; border-radius: 12px; background: var(--bg-card);">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; border-bottom: 1px solid var(--border); padding-bottom: 12px; margin-bottom: 16px;">
            <div>
                <h3 style="margin: 0; font-size: 17px; font-weight: 800;"><?= $btAp ? 'Bayar Faktur Terpilih' : 'Setor Nota Terpilih' ?></h3>
                <p style="margin: 2px 0 0 0; font-size: 12px; color: var(--text-muted);"><?= $btAp ? 'Pemasok' : 'Mitra' ?>: <strong><?= htmlspecialchars($btPihakNama) ?></strong> &middot; <span id="btRingkas"></span></p>
            </div>
            <button type="button" onclick="tutupBayarTerpilih()" style="border: none; background: transparent; font-size: 22px; cursor: pointer; color: var(--text-muted);">&times;</button>
        </div>

        <form id="formBayarTerpilih" onsubmit="simpanBayarTerpilih(event)">
            <input type="hidden" name="<?= $btAp ? 'pemasok_id' : 'mitra_id' ?>" value="<?= (int)$btPihakId ?>">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                <div>
                    <label class="form-label" style="font-size: 12px; font-weight: 700;" for="btTanggal">Tanggal <?= $btAp ? 'Bayar' : 'Setor' ?></label>
                    <input type="date" id="btTanggal" name="tanggal_bayar" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div>
                    <label class="form-label" style="font-size: 12px; font-weight: 700;" for="btMetode">Metode</label>
                    <select id="btMetode" name="metode_bayar" class="form-control" onchange="document.getElementById('btBankWrap').style.display = this.value === 'TRANSFER_BANK' ? 'grid' : 'none'">
                        <option value="TRANSFER_BANK">Transfer Bank</option>
                        <option value="TUNAI" <?= $btAp ? '' : 'selected' ?>>Tunai</option>
                    </select>
                </div>
            </div>
            <div id="btBankWrap" style="display: <?= $btAp ? 'grid' : 'none' ?>; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                <?php if ($btAp): ?>
                    <div><label class="form-label" style="font-size: 12px; font-weight: 700;" for="btBankSumber">Rekening Sumber</label>
                        <input type="text" id="btBankSumber" name="bank_sumber" class="form-control" placeholder="Contoh: BCA 8820192837"></div>
                    <div><label class="form-label" style="font-size: 12px; font-weight: 700;" for="btRekTujuan">Rekening Tujuan Pemasok</label>
                        <input type="text" id="btRekTujuan" name="no_rekening_tujuan" class="form-control" placeholder="Contoh: Mandiri 1320019283"></div>
                <?php else: ?>
                    <div style="grid-column: span 2;"><label class="form-label" style="font-size: 12px; font-weight: 700;" for="btBankTujuan">Rekening Tujuan (milik kita)</label>
                        <input type="text" id="btBankTujuan" name="bank_tujuan" class="form-control" placeholder="Contoh: BCA 8820192837"></div>
                <?php endif; ?>
            </div>
            <div style="margin-bottom: 12px;">
                <label class="form-label" style="font-size: 12px; font-weight: 700;" for="btNominal">Nominal <?= $btAp ? 'Dibayar' : 'Disetor' ?> (Rp)</label>
                <input type="number" id="btNominal" name="jumlah_bayar" class="form-control" min="1" step="any" required oninput="hitungAlokasiTerpilih()"
                       style="font-size: 16px; font-weight: 800; font-family: 'JetBrains Mono', monospace;">
                <div style="font-size: 11.5px; color: var(--text-muted); margin-top: 4px;">Boleh kurang dari total. Nominal dipakai untuk dokumen yang jatuh temponya paling lama lebih dahulu.</div>
            </div>

            <div class="table-responsive" style="max-height: 240px; overflow-y: auto; border: 1px solid var(--border); border-radius: 8px;">
                <table class="table-custom" data-no-tools style="font-size: 12.5px;">
                    <thead><tr>
                        <th><?= $btAp ? 'No. Faktur' : 'No. Surat Jalan / Bon' ?></th><th>Jatuh Tempo</th>
                        <th style="text-align: right;">Sisa</th><th style="text-align: right;"><?= $btAp ? 'Dibayar' : 'Disetor' ?></th><th style="text-align: right;">Sisa Setelah</th>
                    </tr></thead>
                    <tbody id="btBody"></tbody>
                </table>
            </div>

            <div style="margin-top: 12px;">
                <label class="form-label" style="font-size: 12px; font-weight: 700;" for="btKeterangan">Keterangan</label>
                <textarea id="btKeterangan" name="keterangan" class="form-control" rows="2" placeholder="Catatan opsional"></textarea>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 8px; margin-top: 16px; border-top: 1px solid var(--border); padding-top: 14px;">
                <button type="button" class="btn btn-outline" onclick="tutupBayarTerpilih()">Batal</button>
                <button type="submit" id="btSimpan" class="btn btn-primary" style="font-weight: 700;"><?= $btAp ? 'Simpan Pembayaran' : 'Simpan Setoran' ?></button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    const JENIS = <?= json_encode($btJenis) ?>;
    const AKSI = JENIS === 'ap' ? 'save_ap_payment_terpilih' : 'save_ar_payment_terpilih';
    const NAMA_ID = JENIS === 'ap' ? 'invoice_ids[]' : 'nota_ids[]';
    const fmt = n => 'Rp ' + new Intl.NumberFormat('en-US').format(Math.round(n));
    const tgl = s => { const d = String(s || '').slice(0, 10).split('-'); return d.length === 3 ? d[2] + '/' + d[1] + '/' + d[0] : '-'; };
    const esc = s => String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    let terpilih = [];

    const semuaCek = () => [...document.querySelectorAll('.cek-bayar-' + JENIS)];
    const tampil = c => c.closest('tr') && c.closest('tr').offsetParent !== null;

    window.perbaruiTombolBayar = function () {
        const dipilih = semuaCek().filter(c => c.checked);
        const total = dipilih.reduce((s, c) => s + parseFloat(c.dataset.sisa || 0), 0);
        const btn = document.getElementById('btnBayarTerpilih');
        const lbl = document.getElementById('lblBayarTerpilih');
        if (btn) btn.disabled = dipilih.length === 0;
        if (lbl) lbl.textContent = dipilih.length
            ? (JENIS === 'ap' ? 'Bayar ' : 'Setor ') + dipilih.length + ' · ' + fmt(total)
            : (JENIS === 'ap' ? 'Bayar Terpilih' : 'Setor Terpilih');
        const semua = document.getElementById('cekSemuaBayar');
        const terlihat = semuaCek().filter(tampil);
        if (semua) semua.checked = terlihat.length > 0 && terlihat.every(c => c.checked);
    };

    window.pilihSemuaBayar = function (nyala) {
        semuaCek().filter(tampil).forEach(c => { c.checked = nyala; });
        perbaruiTombolBayar();
    };

    window.bukaBayarTerpilih = function () {
        terpilih = semuaCek().filter(c => c.checked).map(c => ({ id: c.value, no: c.dataset.no, sisa: parseFloat(c.dataset.sisa || 0), tempo: c.dataset.tempo, tgl: c.dataset.tgl || '' }));
        if (!terpilih.length) return;
        // urutan pembagian: jatuh tempo paling lama dulu (sama dengan server)
        terpilih.sort((a, b) => (a.tempo + a.tgl + String(a.id).padStart(10, '0')).localeCompare(b.tempo + b.tgl + String(b.id).padStart(10, '0')));
        const total = terpilih.reduce((s, d) => s + d.sisa, 0);
        const nom = document.getElementById('btNominal');
        nom.value = Math.round(total * 100) / 100;
        nom.max = Math.round(total * 100) / 100;
        document.getElementById('btRingkas').textContent = terpilih.length + ' dokumen, total sisa ' + fmt(total);
        hitungAlokasiTerpilih();
        document.getElementById('modalBayarTerpilih').style.display = 'flex';
    };

    window.tutupBayarTerpilih = function () { document.getElementById('modalBayarTerpilih').style.display = 'none'; };

    window.hitungAlokasiTerpilih = function () {
        let uang = parseFloat(document.getElementById('btNominal').value) || 0;
        let tBayar = 0;
        const total = terpilih.reduce((s, d) => s + d.sisa, 0);
        const baris = terpilih.map(d => {
            const bayar = Math.max(0, Math.min(uang, d.sisa));
            uang -= bayar; tBayar += bayar;
            const sisa = d.sisa - bayar;
            const status = bayar <= 0 ? '<span style="color:var(--text-muted);">tidak terbayar</span>'
                         : sisa <= 0.01 ? '<span style="color:var(--ok-fg);font-weight:700;">Lunas</span>' : fmt(sisa);
            return '<tr><td style="font-family:monospace;">' + esc(d.no) + '</td><td>' + tgl(d.tempo) + '</td><td style="text-align:right;">' + fmt(d.sisa) +
                   '</td><td style="text-align:right;font-weight:700;">' + (bayar > 0 ? fmt(bayar) : '-') + '</td><td style="text-align:right;">' + status + '</td></tr>';
        });
        const lebih = (parseFloat(document.getElementById('btNominal').value) || 0) - total;
        baris.push('<tr style="font-weight:800;background:var(--bg-muted);"><td colspan="2">TOTAL</td><td style="text-align:right;">' + fmt(total) +
                   '</td><td style="text-align:right;">' + fmt(tBayar) + '</td><td style="text-align:right;">' + fmt(total - tBayar) + '</td></tr>');
        if (lebih > 0.01) baris.push('<tr><td colspan="5" style="color:var(--bad-fg);font-weight:700;">Nominal melebihi total sisa sebesar ' + fmt(lebih) + '.</td></tr>');
        document.getElementById('btBody').innerHTML = baris.join('');
    };

    window.simpanBayarTerpilih = async function (e) {
        e.preventDefault();
        const form = document.getElementById('formBayarTerpilih');
        const fd = new FormData(form);
        const total = terpilih.reduce((s, d) => s + d.sisa, 0);
        const nominal = parseFloat(fd.get('jumlah_bayar')) || 0;
        if (nominal <= 0) { Swal.fire({ icon: 'warning', title: 'Nominal kosong', text: 'Isi nominal yang lebih besar dari Rp 0.' }); return; }
        if (nominal > total + 0.01) { Swal.fire({ icon: 'warning', title: 'Nominal terlalu besar', text: 'Nominal tidak boleh melebihi total sisa ' + fmt(total) + '.' }); return; }
        terpilih.forEach(d => fd.append(NAMA_ID, d.id));
        const btn = document.getElementById('btSimpan');
        btn.disabled = true;
        const teksAsli = btn.textContent;
        btn.textContent = 'Menyimpan...';
        try {
            const r = await fetch('index.php?action=' + AKSI, { method: 'POST', body: fd });
            const j = await r.json();
            if (!j.success) throw new Error(j.message || 'Gagal menyimpan');
            await Swal.fire({ icon: 'success', title: 'Berhasil', text: j.message });
            window.location.reload();
        } catch (err) {
            Swal.fire({ icon: 'error', title: 'Gagal', text: err.message });
            btn.disabled = false;
            btn.textContent = teksAsli;
        }
    };
    document.addEventListener('DOMContentLoaded', perbaruiTombolBayar);
})();
</script>
