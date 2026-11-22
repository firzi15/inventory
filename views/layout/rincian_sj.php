<?php
// Modal rincian box satu surat jalan (dipakai AR Aging dan AP Aging), dikelompokkan per produk, dengan ekspor Excel dan PDF.
// Buka dengan: bukaRincianSj('keluar', idTransaksi) atau bukaRincianSj('masuk', 'NO-SURAT-JALAN')
?>
<style>
    .rsj-link { cursor: pointer; text-decoration: underline dotted; text-underline-offset: 3px; }
    .rsj-link:hover { color: var(--aktif-fg) !important; }
</style>
<div id="modalRincianSj" class="modal-backdrop" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center; overflow-y: auto; padding: 20px;">
    <div class="card" style="width: 100%; max-width: 1000px; margin: auto; padding: 24px; border-radius: 12px;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; margin-bottom: 12px;">
            <div>
                <h3 id="rsjJudul" style="margin: 0; font-size: 16px; font-weight: 800;">Rincian Surat Jalan</h3>
                <p id="rsjSub" style="margin: 2px 0 0 0; font-size: 12px; color: var(--text-muted);"></p>
            </div>
            <div style="display: flex; align-items: center; gap: 10px;">
                <button type="button" class="btn btn-outline btn-sm" onclick="eksporExcelRincianSj()" style="display: inline-flex; align-items: center; gap: 5px; color: var(--ok-fg); border-color: var(--border-hover); background: var(--ok-bg);" title="Ekspor rincian ke Excel">
                    <i data-lucide="file-spreadsheet" style="width: 14px; height: 14px;"></i><span>Excel</span>
                </button>
                <button type="button" class="btn btn-outline btn-sm" onclick="eksporPdfRincianSj()" style="display: inline-flex; align-items: center; gap: 5px;" title="Ekspor rincian ke PDF">
                    <i data-lucide="file-text" style="width: 14px; height: 14px;"></i><span>PDF</span>
                </button>
                <button type="button" onclick="document.getElementById('modalRincianSj').style.display='none'" style="border: none; background: transparent; font-size: 22px; cursor: pointer; color: var(--text-muted);">&times;</button>
            </div>
        </div>
        <div class="table-responsive" style="max-height: 520px; overflow-y: auto;">
            <table class="table-custom" data-no-tools style="font-size: 12.5px;">
                <thead id="rsjHead"></thead>
                <tbody id="rsjBody"></tbody>
            </table>
        </div>
        <!-- Tabel datar per box khusus ekspor (tidak ditampilkan) -->
        <table id="rsjEkspor" data-no-tools style="display: none;"><thead></thead><tbody></tbody><tfoot></tfoot></table>
    </div>
</div>
<script>
(function () {
    const rp = n => (n < 0 ? '-' : '') + 'Rp ' + new Intl.NumberFormat('en-US').format(Math.abs(Math.round(n)));
    const angka = n => new Intl.NumberFormat('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n);
    const esc = s => String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    const tgl = s => { const d = String(s).slice(0, 10).split('-'); return d[2] + '/' + d[1] + '/' + d[0]; };
    let namaFile = 'Rincian_Surat_Jalan';

    window.bukaRincianSj = async function (tipe, kunci) {
        try {
            const q = new URLSearchParams({ action: 'rincian_sj', tipe: tipe });
            q.set(tipe === 'keluar' ? 'id' : 'sj', kunci);
            const r = await fetch('index.php?' + q.toString());
            const j = await r.json();
            if (!j.success) throw new Error(j.message || 'Gagal memuat rincian');
            const keluar = tipe === 'keluar';
            const k = j.kepala;
            const lHarga = keluar ? 'Harga Jual/kg' : 'Harga Beli/kg';
            const lPihak = keluar ? 'Mitra' : 'Pemasok';

            // tabel tampilan: per produk, lalu box
            const kolomLain = keluar ? '<th>Pemasok &middot; SJ Masuk</th>' : '<th>Status</th>';
            document.getElementById('rsjHead').innerHTML = '<tr><th>Barcode</th>' + kolomLain +
                '<th style="text-align:right;">Netto</th><th style="text-align:right;">' + lHarga + '</th><th style="text-align:right;">Nominal</th></tr>';
            const per = {};
            j.box.forEach(b => { (per[b.produk] = per[b.produk] || { jenis: b.jenis, box: [] }).box.push(b); });
            let tKg = 0, tNom = 0, html = '';
            Object.keys(per).forEach(nama => {
                const g = per[nama];
                let kg = 0, nom = 0;
                g.box.forEach(b => { kg += b.berat_netto; nom += b.nominal; });
                tKg += kg; tNom += nom;
                html += '<tr style="background:var(--bg-muted);font-weight:800;"><td colspan="2">' + esc(nama) + ' <span style="font-weight:600;color:var(--text-muted);">&middot; ' + esc(g.jenis) + ' &middot; ' + g.box.length + ' box</span></td>' +
                        '<td style="text-align:right;">' + angka(kg) + ' kg</td><td></td><td style="text-align:right;">' + rp(nom) + '</td></tr>';
                html += g.box.map(b => '<tr><td style="font-family:monospace;">' + esc(b.id_label) + '</td>' +
                        (keluar ? '<td>' + esc(b.pemasok) + '<div style="font-size:11px;color:var(--text-muted);">' + esc(b.sj_masuk) + '</div></td>'
                                : '<td>' + (b.status === 'AKTIF' ? 'Di gudang' : b.status === 'KELUAR' ? 'Sudah keluar' : esc(b.status)) + '</td>') +
                        '<td style="text-align:right;">' + angka(b.berat_netto) + ' kg</td><td style="text-align:right;">' + rp(b.harga) + '</td>' +
                        '<td style="text-align:right;font-weight:700;">' + rp(b.nominal) + '</td></tr>').join('');
            });
            if (!j.box.length) html = '<tr><td colspan="5" style="text-align:center;padding:20px;color:var(--text-muted);">Tidak ada box</td></tr>';
            else html += '<tr style="font-weight:800;border-top:2px solid var(--border);"><td colspan="2">TOTAL ' + Object.keys(per).length + ' produk &middot; ' + j.box.length + ' box</td>' +
                         '<td style="text-align:right;">' + angka(tKg) + ' kg</td><td></td><td style="text-align:right;">' + rp(tNom) + '</td></tr>';
            document.getElementById('rsjBody').innerHTML = html;

            // tabel ekspor: satu baris per box
            const td = v => '<td>' + esc(v) + '</td>';
            const ek = document.getElementById('rsjEkspor');
            const kepala = ['Produk', 'Jenis', 'Barcode'].concat(keluar ? ['Pemasok', 'SJ Masuk'] : ['Status'], ['Netto (kg)', lHarga, 'Nominal']);
            ek.tHead.innerHTML = '<tr>' + kepala.map(h => '<th>' + esc(h) + '</th>').join('') + '</tr>';
            ek.tBodies[0].innerHTML = j.box.map(b => '<tr>' + [b.produk, b.jenis, b.id_label]
                .concat(keluar ? [b.pemasok, b.sj_masuk] : [b.status === 'AKTIF' ? 'Di gudang' : b.status === 'KELUAR' ? 'Sudah keluar' : b.status],
                        [angka(b.berat_netto), rp(b.harga), rp(b.nominal)]).map(td).join('') + '</tr>').join('');
            const kosong = keluar ? ['', ''] : [''];
            ek.tFoot.innerHTML = j.box.length ? '<tr>' + ['TOTAL', '', j.box.length + ' box'].concat(kosong, [angka(tKg), '', rp(tNom)]).map(td).join('') + '</tr>' : '';

            document.getElementById('rsjJudul').textContent = k.sj + ' · ' + tgl(k.created_at) + ' · ' + (k.pihak || '-');
            document.getElementById('rsjSub').textContent = (keluar ? 'Surat jalan keluar' : 'Surat jalan masuk') + ' · ' + j.box.length + ' box · ' + angka(tKg) + ' kg · ' + rp(tNom);
            namaFile = 'Rincian_' + String(k.sj).replace(/[^\w-]+/g, '_');
            document.getElementById('modalRincianSj').style.display = 'flex';
        } catch (e) { Swal.fire({ icon: 'error', title: 'Gagal', text: e.message }); }
    };
    window.eksporExcelRincianSj = function () { window.eksporExcel(namaFile, 'rsjEkspor'); };
    window.eksporPdfRincianSj = function () {
        window.eksporPdf(document.getElementById('rsjJudul').textContent + ' · ' + document.getElementById('rsjSub').textContent, 'rsjEkspor');
    };
})();
</script>
