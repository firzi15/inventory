/**
 * Ekspor laporan ke Excel (.xlsx) dan PDF.
 * Isi diambil dari tabel yang tampil di halaman, dibersihkan dulu: tanpa kolom Aksi / checkbox,
 * tanpa tombol, ikon, dan emoji. PDF dicetak dari dokumen bersih sendiri, bukan dari halaman aplikasi.
 *
 *   eksporExcel(namaFile, [idTabel])   eksporPdf(judul, [idTabel])
 * Tanpa idTabel: semua tabel yang tampil di area konten (kecuali di dalam modal / data-no-export).
 */
(function () {
    var EMOJI = /[←-⇿⌀-⏿①-➿⤀-⥿⬀-⯿️‍]|[\uD83C-\uD83E][\uDC00-\uDFFF]/g;

    function areaKonten() {
        return document.querySelector('.content-wrapper') || document.querySelector('.app-main-container') || document.body;
    }

    function tabelTarget(id) {
        if (id) {
            var t = document.getElementById(id);
            return t ? [t] : [];
        }
        return Array.prototype.filter.call(areaKonten().querySelectorAll('table'), function (t) {
            return !t.closest('.modal-backdrop') && !t.closest('[data-no-export]') && !t.hasAttribute('data-no-export')
                && t.rows.length > 0 && t.offsetParent !== null;
        });
    }

    function judulTabel(t, i) {
        var teks = '';
        var kartu = t.closest('.card');
        if (kartu && kartu.firstElementChild && !kartu.firstElementChild.contains(t)) {
            teks = kartu.firstElementChild.textContent || '';
        }
        teks = bersihTeks(teks).split('  ')[0];
        return teks || ('Data ' + (i + 1));
    }

    function bersihTeks(s) {
        return String(s || '').replace(EMOJI, '').replace(/\s+/g, ' ').trim();
    }

    function teksSel(td) {
        var c = td.cloneNode(true);
        // isian form diambil nilainya (mis. harga di tabel master), bukan dibuang
        var asli = td.querySelectorAll('input, select, textarea');
        Array.prototype.forEach.call(c.querySelectorAll('input, select, textarea'), function (e, i) {
            var o = asli[i], tipe = (e.getAttribute('type') || '').toLowerCase(), v = '';
            if (tipe === 'checkbox' || tipe === 'radio' || tipe === 'hidden' || tipe === 'button' || tipe === 'submit') { e.remove(); return; }
            if (e.tagName === 'SELECT') v = o && o.selectedOptions && o.selectedOptions[0] ? o.selectedOptions[0].textContent : '';
            else v = o ? o.value : '';
            e.replaceWith(document.createTextNode(' ' + v + ' '));
        });
        Array.prototype.forEach.call(c.querySelectorAll('button, svg, i, input, select, textarea, script, style, .no-print, [data-lucide]'), function (e) { e.remove(); });
        return bersihTeks(c.textContent);
    }

    // "Rp 1.234.567" / "-Rp 1.000" / "Rp -1.000" menjadi angka; selain itu tetap teks
    function nilai(teks) {
        var m = teks.match(/^(-?)\s*Rp\s*(-?)\s*([\d.]+)(?:,(\d+))?$/);
        if (!m) return teks;
        var n = parseFloat(m[3].replace(/\./g, '') + (m[4] ? '.' + m[4] : ''));
        return (m[1] || m[2]) ? -n : n;
    }

    // Kolom yang dibuang: Aksi / Opsi dan kolom checkbox
    function kolomBuang(t) {
        var buang = {};
        var head = t.tHead && t.tHead.rows.length ? t.tHead.rows[t.tHead.rows.length - 1] : t.rows[0];
        var idx = 0;
        Array.prototype.forEach.call(head.cells, function (th) {
            var label = bersihTeks(th.textContent).toLowerCase();
            var hapus = /^(aksi|action|opsi|edit|hapus)$/.test(label) || th.querySelector('input[type=checkbox]') || th.classList.contains('no-print');
            for (var s = 0; s < (th.colSpan || 1); s++) { if (hapus) buang[idx] = true; idx++; }
        });
        return buang;
    }

    function keGrid(t) {
        var buang = kolomBuang(t), grid = [];
        Array.prototype.forEach.call(t.rows, function (tr) {
            if (tr.classList.contains('tt-hidden') || tr.style.display === 'none' || tr.classList.contains('no-print')) return;
            var baris = [], idx = 0;
            Array.prototype.forEach.call(tr.cells, function (td) {
                var teks = teksSel(td);
                for (var s = 0; s < (td.colSpan || 1); s++) {
                    if (!buang[idx]) baris.push(s === 0 ? teks : '');
                    idx++;
                }
            });
            if (baris.some(function (v) { return v !== ''; })) grid.push(baris);
        });
        return grid;
    }

    function kumpulkan(id) {
        var hasil = [];
        tabelTarget(id).forEach(function (t, i) {
            var g = keGrid(t);
            if (g.length) hasil.push({ judul: judulTabel(t, i), grid: g, adaHead: !!(t.tHead && t.tHead.rows.length) });
        });
        // halaman dengan satu tabel: nama sheet memakai judul halaman
        var h1 = document.querySelector('.page-title');
        if (hasil.length === 1 && h1) hasil[0].judul = bersihTeks(h1.textContent);
        return hasil;
    }

    function peringatan(pesan) {
        if (window.showAppAlert) window.showAppAlert({ title: 'Ekspor', text: pesan, icon: 'info' });
        else alert(pesan);
    }

    window.eksporExcel = function (nama, id) {
        if (typeof XLSX === 'undefined') { peringatan('Pustaka Excel belum siap, coba lagi sesaat.'); return; }
        var data = kumpulkan(id);
        if (!data.length) { peringatan('Tidak ada data yang tampil untuk diekspor.'); return; }
        var wb = XLSX.utils.book_new(), dipakai = {};
        data.forEach(function (d) {
            var aoa = d.grid.map(function (r) { return r.map(nilai); });
            var ws = XLSX.utils.aoa_to_sheet(aoa);
            var lebar = [];
            aoa.forEach(function (r) {
                r.forEach(function (v, c) { lebar[c] = Math.max(lebar[c] || 8, Math.min(String(v).length + 2, 50)); });
            });
            ws['!cols'] = lebar.map(function (w) { return { wch: w }; });
            var judul = d.judul.replace(/[\\\/?*\[\]:]/g, ' ').slice(0, 28) || 'Data', n = judul, k = 2;
            while (dipakai[n.toLowerCase()]) n = judul.slice(0, 26) + ' ' + (k++);
            dipakai[n.toLowerCase()] = 1;
            XLSX.utils.book_append_sheet(wb, ws, n);
        });
        var file = nama + '_' + new Date().toISOString().slice(0, 10) + '.xlsx';
        XLSX.writeFile(wb, file);
        if (window.showToast) window.showToast('Berhasil mengekspor ' + file, 'success');
    };

    function esc(s) {
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    window.eksporPdf = function (judul, id) {
        var data = kumpulkan(id);
        if (!data.length) { peringatan('Tidak ada data yang tampil untuk diekspor.'); return; }
        var html = '<!doctype html><html><head><meta charset="utf-8"><title>' + esc(judul) + '</title><style>' +
            '@page{size:A4 landscape;margin:10mm}' +
            'body{font-family:Arial,Helvetica,sans-serif;color:#111;font-size:10.5px;margin:0}' +
            'h1{font-size:16px;margin:0 0 2px}.tgl{font-size:10px;color:#555;margin-bottom:12px}' +
            'h2{font-size:12px;margin:16px 0 6px}' +
            'table{border-collapse:collapse;width:100%;margin-bottom:8px}' +
            'th,td{border:1px solid #bbb;padding:4px 6px;text-align:left;vertical-align:top}' +
            'th{background:#eee;font-weight:700}td.n,th.n{text-align:right}' +
            'tr{page-break-inside:avoid}thead{display:table-header-group}' +
            '</style></head><body><h1>' + esc(judul) + '</h1><div class="tgl">Dicetak ' + esc(new Date().toLocaleString('id-ID')) + '</div>';
        data.forEach(function (d) {
            if (data.length > 1) html += '<h2>' + esc(d.judul) + '</h2>';
            html += '<table>';
            d.grid.forEach(function (r, ri) {
                var tag = (ri === 0 && d.adaHead) ? 'th' : 'td';
                html += '<tr>' + r.map(function (v) {
                    var num = typeof nilai(v) === 'number' || /^-?[\d.,]+%?$/.test(v);
                    return '<' + tag + (num ? ' class="n"' : '') + '>' + esc(v) + '</' + tag + '>';
                }).join('') + '</tr>';
            });
            html += '</table>';
        });
        html += '</body></html>';

        var f = document.createElement('iframe');
        f.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;';
        document.body.appendChild(f);
        var doc = f.contentWindow.document;
        doc.open(); doc.write(html); doc.close();
        setTimeout(function () {
            f.contentWindow.focus();
            f.contentWindow.print();
            setTimeout(function () { f.remove(); }, 2000);
        }, 250);
    };

    // Kompatibel dengan tombol lama
    window.exportTableToExcel = function (id, nama) { window.eksporExcel(nama, id); };
})();
